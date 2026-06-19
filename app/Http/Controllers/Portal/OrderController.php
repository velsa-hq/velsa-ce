<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ExhibitorOrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Services\Accounting\InvoiceService;
use App\Services\Payments\OrderPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function show(Request $request, ExhibitorOrder $order): Response
    {

        $order->load(['items.equipmentItem.category', 'payments']);

        return Inertia::render('portal/order-show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status?->value,
                'subtotal_cents' => $order->subtotal_cents,
                'tax_cents' => $order->tax_cents,
                'total_cents' => $order->total_cents,
                'paid_cents' => $order->paid_cents,
                'balance_cents' => max(0, $order->total_cents - $order->paid_cents),
                'placed_at' => $order->placed_at?->toIso8601String(),
                'is_editable' => $this->isEditable($order),
                'items' => $order->items->map(fn (ExhibitorOrderItem $i) => [
                    'id' => $i->id,
                    'sku' => $i->sku,
                    'name' => $i->name,
                    'quantity' => $i->quantity,
                    'unit_price_cents' => $i->unit_price_cents,
                    'line_total_cents' => $i->line_total_cents,
                ]),
                'payments' => $order->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount_cents' => $p->amount_cents,
                    'card_brand' => $p->card_brand,
                    'last4' => $p->last4,
                    'captured_at' => $p->processed_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function addItem(Request $request, InvoiceService $invoices): RedirectResponse
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        $data = $request->validate([
            'equipment_item_id' => ['required', 'integer', 'exists:equipment_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $item = EquipmentItem::query()->where('is_active', true)
            ->findOrFail($data['equipment_item_id']);

        $order = DB::transaction(function () use ($exhibitor, $item, $data, $invoices) {
            $order = $exhibitor->currentDraftOrder();
            if ($order === null) {
                $order = $exhibitor->orders()->create([
                    'status' => ExhibitorOrderStatus::Pending->value,
                    'placed_at' => now(),
                ]);
            }
            abort_unless($this->isEditable($order), 422, 'Order is no longer editable.');

            ExhibitorOrderItem::fromCatalog($order, $item, (int) $data['quantity']);
            $order->recalculateTotals();

            // issue an invoice once the order has billable items (idempotent)
            if ($order->total_cents > 0) {
                $invoices->issueForOrder($order->fresh());
            }

            return $order;
        });

        return redirect()
            ->route('portal.orders.show', $order->id)
            ->with('toast', [
                'type' => 'success',
                'message' => "Added {$data['quantity']} x {$item->name}.",
            ]);
    }

    public function removeItem(Request $request, ExhibitorOrder $order, ExhibitorOrderItem $item): RedirectResponse
    {
        abort_unless($this->isEditable($order), 422, 'Order is no longer editable.');

        DB::transaction(function () use ($order, $item) {
            $item->delete();
            $order->recalculateTotals();
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Item removed.']);
    }

    /**
     * Hosted-iframe payment page. Production embeds the payment processor's tokenization
     * iframe; the preview driver renders a fake form posting to processPayment.
     */
    public function pay(Request $request, ExhibitorOrder $order): Response|RedirectResponse
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        if ($order->balanceCents() <= 0) {
            return redirect()
                ->route('portal.orders.show', $order->id)
                ->with('toast', ['type' => 'success', 'message' => 'Order is already paid.']);
        }

        return Inertia::render('portal/order-pay', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'subtotal_cents' => $order->subtotal_cents,
                'tax_cents' => $order->tax_cents,
                'total_cents' => $order->total_cents,
                'paid_cents' => $order->paid_cents,
                'balance_cents' => $order->balanceCents(),
            ],
            'exhibitor' => [
                'company_name' => $exhibitor->company_name,
                'email' => $exhibitor->email,
            ],
        ]);
    }

    /**
     * Card token comes from the client-side iframe; a real processor integration
     * receives it via postMessage from the processor's hosted iframe.
     */
    public function processPayment(Request $request, ExhibitorOrder $order, OrderPaymentService $payments): RedirectResponse
    {

        $data = $request->validate([
            'card_token' => ['required', 'string', 'max:120'],
            'amount_cents' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($order->balanceCents() <= 0) {
            return redirect()
                ->route('portal.orders.show', $order->id)
                ->with('toast', ['type' => 'success', 'message' => 'Order is already paid.']);
        }

        $amount = isset($data['amount_cents'])
            ? min((int) $data['amount_cents'], $order->balanceCents())
            : $order->balanceCents();

        $payment = $payments->charge($order, $data['card_token'], $amount);

        if ($payment->status->value === PaymentStatus::Captured->value) {
            return redirect()
                ->route('portal.orders.show', $order->id)
                ->with('toast', [
                    'type' => 'success',
                    'message' => sprintf(
                        'Payment of $%s captured. Card ending %s.',
                        number_format($payment->amount_cents / 100, 2),
                        $payment->last4 ?? '????',
                    ),
                ]);
        }

        return back()->with('toast', [
            'type' => 'error',
            'message' => 'Payment declined: '.($payment->failure_reason ?? 'unknown reason'),
        ]);
    }

    /**
     * Print-optimized invoice view. Stand-alone HTML (no portal layout) so the
     * browser's native "Print to PDF" produces a clean single-page document.
     */
    public function invoice(Request $request, ExhibitorOrder $order): Response
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        $order->load(['items.equipmentItem', 'payments']);
        $exhibitor->load('event.booking.venue');

        return Inertia::render('portal/invoice', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status?->value,
                'subtotal_cents' => $order->subtotal_cents,
                'tax_cents' => $order->tax_cents,
                'total_cents' => $order->total_cents,
                'paid_cents' => $order->paid_cents,
                'balance_cents' => $order->balanceCents(),
                'placed_at' => $order->placed_at?->toIso8601String(),
                'items' => $order->items->map(fn (ExhibitorOrderItem $i) => [
                    'sku' => $i->sku,
                    'name' => $i->name,
                    'quantity' => $i->quantity,
                    'unit_price_cents' => $i->unit_price_cents,
                    'line_total_cents' => $i->line_total_cents,
                ]),
                'payments' => $order->payments->map(fn ($p) => [
                    'amount_cents' => $p->amount_cents,
                    'card_brand' => $p->card_brand,
                    'last4' => $p->last4,
                    'captured_at' => $p->processed_at?->toIso8601String(),
                ]),
            ],
            'exhibitor' => [
                'company_name' => $exhibitor->company_name,
                'contact_name' => $exhibitor->contact_name,
                'email' => $exhibitor->email,
                'phone' => $exhibitor->phone,
                'booth_assignment' => $exhibitor->booth_assignment,
                'address' => $exhibitor->address_json,
            ],
            'event' => $exhibitor->event ? [
                'name' => $exhibitor->event->name,
                'booking' => $exhibitor->event->booking ? [
                    'reference' => $exhibitor->event->booking->reference,
                    'name' => $exhibitor->event->booking->name,
                    'venue_name' => $exhibitor->event->booking->venue?->name,
                    'start_at' => $exhibitor->event->booking->start_at?->toIso8601String(),
                    'end_at' => $exhibitor->event->booking->end_at?->toIso8601String(),
                ] : null,
            ] : null,
        ]);
    }

    protected function isEditable(ExhibitorOrder $order): bool
    {
        // only unpaid / partially-paid orders may be modified
        return in_array($order->status?->value, [
            ExhibitorOrderStatus::Pending->value,
            ExhibitorOrderStatus::PartiallyPaid->value,
        ], true);
    }
}
