<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ReasonValidationRules;
use App\Concerns\RefundValidationRules;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordManualPaymentRequest;
use App\Models\Booking;
use App\Models\Exhibitor;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorPayment;
use App\Models\Invoice;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\ValueFormatter;
use App\Services\Payments\OrderPaymentService;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class InvoiceController extends Controller
{
    use ReasonValidationRules;
    use RefundValidationRules;

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: null;
        $stage = $request->string('stage')->toString() ?: null;

        $query = Invoice::query()
            ->with(['invoiceable'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($stage, fn ($q, $s) => $q->where('dunning_stage', $s))
            ->orderByRaw('CASE WHEN due_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_on')
            ->orderByDesc('id');

        $invoices = $query->paginate(50)->withQueryString();

        $rows = $invoices->getCollection()->map(function (Invoice $inv) {
            $sourceLabel = $this->sourceLabel($inv);

            return [
                'id' => $inv->id,
                'number' => $inv->number,
                'status' => $inv->status?->value,
                'status_label' => $inv->status?->label(),
                'dunning_stage' => $inv->dunning_stage?->value,
                'dunning_label' => $inv->dunning_stage?->label(),
                'source' => $sourceLabel,
                'subtotal_cents' => $inv->subtotal_cents,
                'tax_cents' => $inv->tax_cents,
                'total_cents' => $inv->total_cents,
                'paid_cents' => $inv->paid_cents,
                'balance_cents' => $inv->balanceCents(),
                'issued_on' => $inv->issued_on?->toDateString(),
                'due_on' => $inv->due_on?->toDateString(),
                'days_past_due' => $inv->daysPastDue(),
                'is_past_due' => $inv->isPastDue(),
            ];
        });

        // rollups for the page header
        $summary = [
            'total_outstanding_cents' => (int) Invoice::query()
                ->open()->sum(\DB::raw('total_cents - paid_cents')),
            'past_due_cents' => (int) Invoice::query()
                ->pastDue()->sum(\DB::raw('total_cents - paid_cents')),
            'open_count' => Invoice::query()->open()->count(),
            'past_due_count' => Invoice::query()->pastDue()->count(),
        ];

        return Inertia::render('admin/invoices/index', [
            'invoices' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'total' => $invoices->total(),
                ],
                'links' => [
                    'prev' => $invoices->previousPageUrl(),
                    'next' => $invoices->nextPageUrl(),
                ],
            ],
            'filters' => ['status' => $status, 'stage' => $stage],
            'statuses' => array_map(
                fn (InvoiceStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                InvoiceStatus::cases(),
            ),
            'summary' => $summary,
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load(['invoiceable', 'issuedBy', 'lines']);

        $payments = collect();
        if ($invoice->invoiceable instanceof ExhibitorOrder) {
            $payments = $invoice->invoiceable->payments()->orderByDesc('id')->get();
        }

        return Inertia::render('admin/invoices/show', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status?->value,
                'status_label' => $invoice->status?->label(),
                'dunning_stage' => $invoice->dunning_stage?->value,
                'dunning_label' => $invoice->dunning_stage?->label(),
                'source' => $this->sourceLabel($invoice),
                'source_link' => $this->sourceLink($invoice),
                'source_kind' => match (true) {
                    $invoice->invoiceable instanceof ExhibitorOrder => 'exhibitor_order',
                    $invoice->invoiceable instanceof Booking => 'booking',
                    default => 'other',
                },
                'subtotal_cents' => $invoice->subtotal_cents,
                'tax_cents' => $invoice->tax_cents,
                'total_cents' => $invoice->total_cents,
                'paid_cents' => $invoice->paid_cents,
                'balance_cents' => $invoice->balanceCents(),
                'issued_on' => $invoice->issued_on?->toDateString(),
                'due_on' => $invoice->due_on?->toDateString(),
                'sent_at' => $invoice->sent_at?->toIso8601String(),
                'paid_at' => $invoice->paid_at?->toIso8601String(),
                'voided_at' => $invoice->voided_at?->toIso8601String(),
                'void_reason' => $invoice->void_reason,
                'net_days' => $invoice->net_days,
                'notes' => $invoice->notes,
                'customer_reference' => $invoice->customer_reference,
                'internal_reference' => $invoice->internal_reference,
                'lines' => $invoice->lines->map(fn ($l) => [
                    'id' => $l->id,
                    'description' => $l->description,
                    'detail' => $l->detail,
                    'quantity' => $l->quantity,
                    'unit_price_cents' => $l->unit_price_cents,
                    'line_total_cents' => $l->line_total_cents,
                    'reference' => $l->reference,
                ])->all(),
                'issued_by' => $invoice->issuedBy?->name,
                'days_past_due' => $invoice->daysPastDue(),
                'payments' => $payments->map(fn ($p) => [
                    'id' => $p->id,
                    'provider' => $p->provider,
                    'amount_cents' => $p->amount_cents,
                    'refunded_amount_cents' => $p->refunded_amount_cents ?? 0,
                    'refundable_cents' => method_exists($p, 'refundableAmountCents') ? $p->refundableAmountCents() : 0,
                    'is_fully_refunded' => method_exists($p, 'isFullyRefunded') ? $p->isFullyRefunded() : false,
                    'status' => $p->status?->value,
                    'card_brand' => $p->card_brand,
                    'last4' => $p->last4,
                    'captured_at' => $p->captured_at?->toIso8601String(),
                    'refunded_at' => $p->refunded_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Invoice PDF. One blade template for both source kinds.
     */
    public function downloadPdf(Invoice $invoice): PdfBuilder
    {
        $invoice->load(['invoiceable', 'lines']);

        return Pdf::view('pdf.invoice', [
            'invoice' => $invoice,
            'appName' => (string) config('app.name'),
            'appSubtitle' => (string) app(SystemSettings::class)
                ->get('branding.app_subtitle', ''),
            'fromEmail' => (string) config('mail.from.address'),
            'sourceLabel' => $this->sourceLabel($invoice),
            'billToName' => $this->billToName($invoice),
            'billToContact' => $this->billToContact($invoice),
            'billToEmail' => app(InvoiceService::class)->resolveInvoiceEmail($invoice),
            'lineItems' => $invoice->lines->map(fn ($l) => [
                'description' => $l->description,
                'detail' => $l->detail ?? '',
                'amount_cents' => (int) $l->line_total_cents,
                'reference' => $l->reference,
            ])->all(),
        ])->name("invoice-{$invoice->number}.pdf");
    }

    /**
     * Record an off-rail payment (check/wire/cash/ACH). recordManual dispatches
     * PaymentCaptured, which drives journal entries + receipt + invoice refresh.
     */
    public function recordPayment(
        RecordManualPaymentRequest $request,
        Invoice $invoice,
        OrderPaymentService $payments,
        InvoiceService $invoices,
    ): RedirectResponse {
        $data = $request->validated();

        $source = $invoice->invoiceable;

        try {
            if ($source instanceof ExhibitorOrder) {
                // exhibitor orders carry their own ExhibitorPayment rows, so
                // manual payments record against the order
                $payments->recordManual(
                    order: $source,
                    amountCents: (int) $data['amount_cents'],
                    method: $data['method'],
                    reference: $data['reference'] ?? null,
                    note: $data['note'] ?? null,
                    userId: $request->user()?->id,
                );
            } else {
                // booking/client/other post at the invoice level (cash debit, AR credit)
                $invoices->applyPaymentToInvoice(
                    invoice: $invoice,
                    amountCents: (int) $data['amount_cents'],
                    method: $data['method'],
                    reference: $data['reference'] ?? null,
                    note: $data['note'] ?? null,
                    userId: $request->user()?->id,
                );
            }
        } catch (\RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => sprintf(
                'Recorded $%s %s payment.',
                ValueFormatter::dollars($data['amount_cents']),
                $data['method'],
            ),
        ]);
    }

    /**
     * Invoice-level refund for Booking/Client invoices with no payment row.
     * ExhibitorOrder invoices route through refund() against a specific payment.
     */
    public function refundInvoice(
        Request $request,
        Invoice $invoice,
        InvoiceService $invoices,
    ): RedirectResponse {
        $source = $invoice->invoiceable;
        if ($source instanceof ExhibitorOrder) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Exhibitor-order refunds must be applied to a specific payment.',
            ]);
        }

        $data = $request->validate($this->refundRules());

        try {
            $invoices->refundInvoice(
                invoice: $invoice,
                amountCents: (int) $data['amount_cents'],
                reason: $data['reason'] ?? null,
                userId: $request->user()?->id,
            );
        } catch (\RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => sprintf(
                'Refunded $%s on invoice %s.',
                ValueFormatter::dollars($data['amount_cents']),
                $invoice->number,
            ),
        ]);
    }

    /**
     * Refund all or part of a captured exhibitor payment: reversing journal
     * pair, walk back paid_cents, refresh invoice. Processor-backed payments hit the
     * processor's refund(); manual payments record bookkeeping only (finance
     * handles the physical reversal).
     */
    public function refund(
        Request $request,
        Invoice $invoice,
        ExhibitorPayment $payment,
        OrderPaymentService $payments,
    ): RedirectResponse {
        $source = $invoice->invoiceable;
        if (! $source instanceof ExhibitorOrder) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Refunds are only supported on exhibitor-order invoices today.',
            ]);
        }
        if ($payment->exhibitor_order_id !== $source->id) {
            abort(404);
        }

        $data = $request->validate($this->refundRules());

        try {
            $payments->refund(
                payment: $payment,
                amountCents: (int) $data['amount_cents'],
                reason: $data['reason'] ?? null,
                userId: $request->user()?->id,
            );
        } catch (\RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => sprintf(
                'Refunded $%s on payment #%d.',
                ValueFormatter::dollars($data['amount_cents']),
                $payment->id,
            ),
        ]);
    }

    /**
     * Write off an uncollectable invoice (terminal). Posts bad-debt entry
     * (debit 5900, credit 1100).
     */
    public function writeOff(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => $this->reasonRule(true),
        ]);

        try {
            $service->writeOff($invoice, $data['reason'], $request->user()?->id);
        } catch (\RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Invoice {$invoice->number} written off.",
        ]);
    }

    /**
     * Issue a deposit invoice for a booking (deposit_percent of total). Idempotent.
     */
    public function issueBookingDeposit(Request $request, Booking $booking, InvoiceService $service): RedirectResponse
    {
        try {
            $invoice = $service->issueDepositForBooking($booking, $request->user()?->id);
        } catch (\RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('toast', ['type' => 'success', 'message' => "Deposit invoice {$invoice->number} issued."]);
    }

    /**
     * Issue the final-balance invoice for a booking (total less already-invoiced).
     */
    public function issueBookingBalance(Request $request, Booking $booking, InvoiceService $service): RedirectResponse
    {
        try {
            $invoice = $service->issueBalanceForBooking($booking, $request->user()?->id);
        } catch (\RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('toast', ['type' => 'success', 'message' => "Balance invoice {$invoice->number} issued."]);
    }

    /**
     * Inline edit for customer_reference (their PO) and internal_reference
     * (project/event code). Editable in every status so a PO can attach post-issuance.
     */
    public function updateReferences(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'customer_reference' => ['nullable', 'string', 'max:255'],
            'internal_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice->forceFill([
            'customer_reference' => $data['customer_reference'] ?? null,
            'internal_reference' => $data['internal_reference'] ?? null,
        ])->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'References updated.']);
    }

    public function void(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => $this->reasonRule(true),
        ]);

        try {
            $service->void($invoice, $data['reason'], $request->user()?->id);
        } catch (\RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => "Invoice {$invoice->number} voided."]);
    }

    /**
     * Statement view: all of an exhibitor's invoices rolled into one document
     * with aging buckets and totals.
     */
    public function statement(Exhibitor $exhibitor): Response
    {
        $orderIds = $exhibitor->orders()->pluck('id');

        $invoices = Invoice::query()
            ->where('invoiceable_type', ExhibitorOrder::class)
            ->whereIn('invoiceable_id', $orderIds)
            ->orderBy('issued_on')
            ->get();

        $totals = [
            'total_cents' => (int) $invoices->sum('total_cents'),
            'paid_cents' => (int) $invoices->sum('paid_cents'),
            'balance_cents' => (int) $invoices->sum(fn (Invoice $i) => $i->balanceCents()),
            'past_due_cents' => (int) $invoices
                ->filter(fn (Invoice $i) => $i->isPastDue())
                ->sum(fn (Invoice $i) => $i->balanceCents()),
        ];

        return Inertia::render('admin/invoices/statement', [
            'exhibitor' => [
                'id' => $exhibitor->id,
                'company_name' => $exhibitor->company_name,
                'contact_name' => $exhibitor->contact_name,
                'email' => $exhibitor->email,
                'phone' => $exhibitor->phone,
                'booth_assignment' => $exhibitor->booth_assignment,
            ],
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'status' => $i->status?->value,
                'status_label' => $i->status?->label(),
                'dunning_label' => $i->dunning_stage?->label(),
                'issued_on' => $i->issued_on?->toDateString(),
                'due_on' => $i->due_on?->toDateString(),
                'total_cents' => $i->total_cents,
                'paid_cents' => $i->paid_cents,
                'balance_cents' => $i->balanceCents(),
                'days_past_due' => $i->daysPastDue(),
            ]),
            'totals' => $totals,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    protected function sourceLabel(Invoice $invoice): string
    {
        $source = $invoice->invoiceable;
        if ($source instanceof ExhibitorOrder) {
            $name = $source->exhibitor?->company_name ?? 'Exhibitor';

            return "{$name} · {$source->order_number}";
        }
        if ($source instanceof Booking) {
            $client = $source->client?->name ?? 'Client';
            $kind = $invoice->notes ? ' ('.$invoice->notes.')' : '';

            return "{$client} · {$source->reference}{$kind}";
        }

        return class_basename($invoice->invoiceable_type).' #'.$invoice->invoiceable_id;
    }

    /**
     * @return array{label: string, url: string}|null
     */
    protected function sourceLink(Invoice $invoice): ?array
    {
        $source = $invoice->invoiceable;
        if ($source instanceof ExhibitorOrder && $source->exhibitor) {
            return [
                'label' => 'View order',
                'url' => "/exhibitors/{$source->exhibitor_id}/orders/{$source->id}",
            ];
        }
        if ($source instanceof Booking) {
            return [
                'label' => 'View booking',
                'url' => "/bookings/{$source->id}",
            ];
        }

        return null;
    }

    protected function billToName(Invoice $invoice): ?string
    {
        $source = $invoice->invoiceable;
        if ($source instanceof ExhibitorOrder) {
            return $source->exhibitor?->company_name;
        }
        if ($source instanceof Booking) {
            return $source->client?->name;
        }

        return null;
    }

    protected function billToContact(Invoice $invoice): ?string
    {
        $source = $invoice->invoiceable;
        if ($source instanceof ExhibitorOrder) {
            return $source->exhibitor?->contact_name;
        }
        if ($source instanceof Booking) {
            $primary = $source->client?->contacts()->where('is_primary', true)->first();

            return $primary?->name;
        }

        return null;
    }
}
