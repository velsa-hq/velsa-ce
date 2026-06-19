<?php

namespace App\Services\Accounting;

use App\Enums\DunningStage;
use App\Enums\InvoiceStatus;
use App\Mail\DunningNotice;
use App\Mail\InvoiceRefunded;
use App\Mail\IssuedInvoice;
use App\Models\Booking;
use App\Models\ExhibitorOrder;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Single entry point for invoice lifecycle changes. Holds the state-machine
 * rules (Draft -> Issued -> PartialPaid -> Paid/PastDue/Void) and writes an
 * audit row for every transition. Polymorphic source via `invoiceable`
 * (exhibitor orders and bookings).
 */
class InvoiceService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * Idempotently issue an invoice for an exhibitor order. If one
     * already exists for this order, return the existing record and
     * refresh its totals from the order. Otherwise create one.
     */
    public function issueForOrder(ExhibitorOrder $order, ?int $userId = null, int $netDays = 30): Invoice
    {
        if ($order->total_cents <= 0) {
            throw new RuntimeException('Cannot issue an invoice for an order with no items.');
        }

        return DB::transaction(function () use ($order, $userId, $netDays) {
            $invoice = Invoice::query()
                ->where('invoiceable_type', ExhibitorOrder::class)
                ->where('invoiceable_id', $order->id)
                ->first();

            if ($invoice === null) {
                $invoice = Invoice::query()->create([
                    'invoiceable_type' => ExhibitorOrder::class,
                    'invoiceable_id' => $order->id,
                    'status' => InvoiceStatus::Issued->value,
                    'dunning_stage' => DunningStage::None->value,
                    'subtotal_cents' => $order->subtotal_cents,
                    'tax_cents' => $order->tax_cents,
                    'total_cents' => $order->total_cents,
                    'paid_cents' => $order->paid_cents,
                    'issued_on' => now()->toDateString(),
                    'due_on' => now()->addDays($netDays)->toDateString(),
                    'sent_at' => now(),
                    'net_days' => $netDays,
                    'issued_by_user_id' => $userId,
                ]);

                // itemize the order onto invoice lines so the PDF and
                // statement render off the invoice row
                $position = 0;
                foreach ($order->items as $item) {
                    InvoiceLine::create([
                        'invoice_id' => $invoice->id,
                        'position' => $position++,
                        'description' => $item->name,
                        'detail' => $item->quantity > 1
                            ? "{$item->quantity} x \$".number_format($item->unit_price_cents / 100, 2)
                            : null,
                        'quantity' => $item->quantity,
                        'unit_price_cents' => $item->unit_price_cents,
                    ]);
                }

                $this->auditLogger->record(
                    eventType: 'invoice.issued',
                    subject: $invoice,
                    payload: [
                        'invoiceable_type' => ExhibitorOrder::class,
                        'invoiceable_id' => $order->id,
                        'total_cents' => $order->total_cents,
                        'due_on' => $invoice->due_on?->toDateString(),
                    ],
                );

                $this->postIssuanceJournal($invoice, $userId);
            } else {
                // refresh totals from the order; it may have changed
                // before payment was captured
                if ($invoice->status === InvoiceStatus::Draft) {
                    $invoice->update([
                        'subtotal_cents' => $order->subtotal_cents,
                        'tax_cents' => $order->tax_cents,
                        'total_cents' => $order->total_cents,
                    ]);
                }
            }

            return $invoice->fresh();
        });
    }

    /**
     * Recompute paid_cents + status from the source order's payment
     * total. Called from a PaymentCaptured listener so invoices stay
     * in lockstep with captured payments without having to walk the
     * payment table per invoice query.
     */
    public function refreshFromSource(Invoice $invoice): Invoice
    {
        $source = $invoice->invoiceable;
        if (! $source instanceof Model) {
            return $invoice;
        }

        $paid = (int) ($source->paid_cents ?? 0);
        $total = (int) ($source->total_cents ?? $invoice->total_cents);

        $newStatus = match (true) {
            $paid >= $total && $total > 0 => InvoiceStatus::Paid,
            $paid > 0 => InvoiceStatus::PartialPaid,
            $invoice->isPastDue() => InvoiceStatus::PastDue,
            default => InvoiceStatus::Issued,
        };

        $changed = $invoice->paid_cents !== $paid || $invoice->status !== $newStatus;

        $invoice->update([
            'paid_cents' => $paid,
            'status' => $newStatus->value,
            'paid_at' => $newStatus === InvoiceStatus::Paid && $invoice->paid_at === null
                ? now()
                : $invoice->paid_at,
        ]);

        if ($changed) {
            $this->auditLogger->record(
                eventType: 'invoice.status_changed',
                subject: $invoice->fresh(),
                payload: [
                    'paid_cents' => $paid,
                    'status' => $newStatus->value,
                ],
            );
        }

        return $invoice->fresh();
    }

    /**
     * Void an invoice that was issued in error. Voiding does NOT
     * imply a refund - payments captured before voiding stay on
     * the underlying source.
     */
    public function void(Invoice $invoice, string $reason, ?int $userId = null): Invoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new RuntimeException('Cannot void a paid invoice. Refund first.');
        }
        if ($invoice->status === InvoiceStatus::Void) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $reason, $userId) {
            $invoice->update([
                'status' => InvoiceStatus::Void->value,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            // reverse the issuance accrual so a cancelled invoice leaves no
            // revenue or receivable; any payment taken stays as a credit on
            // A/R (a refund owed)
            if ($invoice->revenue_posted_at !== null) {
                $postedOn = now()->toDateString();
                $arAccount = config('accounting.posting.ar_account', '1100');
                $taxAccount = config('accounting.posting.tax_account', '2200');

                JournalEntry::post([
                    'account_code' => $this->revenueAccountFor($invoice),
                    'description' => "Revenue reversed - voided invoice {$invoice->number}",
                    'debit_cents' => $invoice->subtotal_cents,
                    'posted_on' => $postedOn,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'posted_by_user_id' => $userId,
                ]);
                if ($invoice->tax_cents > 0) {
                    JournalEntry::post([
                        'account_code' => $taxAccount,
                        'description' => "Sales tax reversed - voided invoice {$invoice->number}",
                        'debit_cents' => $invoice->tax_cents,
                        'posted_on' => $postedOn,
                        'source_type' => Invoice::class,
                        'source_id' => $invoice->id,
                        'posted_by_user_id' => $userId,
                    ]);
                }
                JournalEntry::post([
                    'account_code' => $arAccount,
                    'description' => "A/R reversed - voided invoice {$invoice->number}",
                    'credit_cents' => $invoice->total_cents,
                    'posted_on' => $postedOn,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                    'posted_by_user_id' => $userId,
                ]);
            }

            $this->auditLogger->record(
                eventType: 'invoice.voided',
                subject: $invoice->fresh(),
                payload: ['reason' => $reason, 'voided_by_user_id' => $userId],
            );

            return $invoice->fresh();
        });
    }

    /**
     * Advance an open invoice's dunning_stage based on days past due.
     * Returns null when the stage was already correct (no change).
     * Used by the nightly DunningCommand; centralized here so the
     * audit row is always written alongside the stage change.
     */
    public function advanceDunning(Invoice $invoice): ?Invoice
    {
        if (! $invoice->status->isOpen()) {
            return null;
        }

        $daysPastDue = $invoice->daysPastDue();
        $newStage = DunningStage::fromDaysPastDue($daysPastDue);

        if ($newStage === $invoice->dunning_stage) {
            return null;
        }

        // Capture pre-update values for the audit row.
        $fromStage = $invoice->dunning_stage->value;

        // Move PastDue status too, so the invoice scopes line up.
        $updates = ['dunning_stage' => $newStage->value];
        if ($daysPastDue > 0 && $invoice->status === InvoiceStatus::Issued) {
            $updates['status'] = InvoiceStatus::PastDue->value;
        }
        $invoice->update($updates);

        $this->auditLogger->record(
            eventType: 'invoice.dunning_advanced',
            subject: $invoice->fresh(),
            payload: [
                'from_stage' => $fromStage,
                'to_stage' => $newStage->value,
                'days_past_due' => $daysPastDue,
            ],
        );

        $this->sendDunningEmail($invoice->fresh(), $newStage);

        return $invoice->fresh();
    }

    /**
     * Send a dunning email matching the new stage. Skipped if no
     * email is on file for the invoiced party, or when transitioning
     * back to None (recovery scenario - no notice needed).
     */
    protected function sendDunningEmail(Invoice $invoice, DunningStage $stage): void
    {
        if ($stage === DunningStage::None) {
            return;
        }

        $email = $this->resolveInvoiceEmail($invoice);
        if (empty($email)) {
            return;
        }

        Mail::to($email)->send(new DunningNotice($invoice, $stage));
    }

    /**
     * Send a refund notification to the invoiced party. Public so
     * OrderPaymentService can call it after a payment-level refund
     * has flowed back into the invoice. No-op when no email is on
     * file - the audit row still captures the action.
     */
    public function sendRefundNotice(Invoice $invoice, int $amountCents, ?string $reason = null): void
    {
        $email = $this->resolveInvoiceEmail($invoice);
        if (empty($email)) {
            return;
        }

        Mail::to($email)->send(new InvoiceRefunded($invoice, $amountCents, $reason));
    }

    /**
     * Resolve the contact email for an invoice's source. Used by both
     * the dunning email and the payment-receipt listener - single
     * lookup point so booking-sourced invoices behave like
     * exhibitor-sourced ones.
     */
    public function resolveInvoiceEmail(Invoice $invoice): ?string
    {
        $source = $invoice->invoiceable;

        if ($source instanceof ExhibitorOrder) {
            return $source->exhibitor?->email;
        }

        if ($source instanceof Booking) {
            // Booking -> Client -> primary Contact -> email. Falls back
            // to any contact's email if no primary is flagged.
            $client = $source->client;
            if ($client === null) {
                return null;
            }
            $primary = $client->contacts()
                ->where('is_primary', true)
                ->whereNotNull('email')
                ->first();
            if ($primary !== null) {
                return $primary->email;
            }

            return $client->contacts()->whereNotNull('email')->value('email');
        }

        return null;
    }

    // Booking-sourced invoicing: a booking can carry multiple invoices
    // (deposit + balance + installments).

    /**
     * Issue the deposit invoice for a booking. Deposit amount =
     * booking.total_cents x booking.deposit_percent / 100. Idempotent
     * on (booking, 'deposit') notes-key so a re-run returns the
     * existing record.
     */
    public function issueDepositForBooking(Booking $booking, ?int $userId = null, int $netDays = 30): Invoice
    {
        if ($booking->total_cents <= 0) {
            throw new RuntimeException('Cannot invoice a booking with no total.');
        }

        $depositCents = (int) round($booking->total_cents * (float) $booking->deposit_percent / 100);

        return $this->createBookingInvoice($booking, $depositCents, 'deposit', $userId, $netDays);
    }

    /**
     * Issue the final-balance invoice for a booking. Amount = booking
     * total minus everything already invoiced (excluding voided +
     * written-off). Idempotent on the 'balance' notes-key.
     */
    public function issueBalanceForBooking(Booking $booking, ?int $userId = null, int $netDays = 30): Invoice
    {
        $remaining = $booking->remainingToInvoiceCents();
        if ($remaining <= 0) {
            throw new RuntimeException('Booking is already fully invoiced.');
        }

        return $this->createBookingInvoice($booking, $remaining, 'balance', $userId, $netDays);
    }

    /**
     * Issue a Booking invoice for a single Installment on its
     * PaymentSchedule. Idempotent - re-running returns the existing
     * invoice. Caller (IssueDueInstallments command) is expected to
     * stamp `invoice_id` + `invoiced_at` on the Installment row after
     * the invoice is returned.
     */
    public function issueInstallmentForBooking(
        Booking $booking,
        Installment $installment,
        ?int $userId = null,
        int $netDays = 14,
    ): Invoice {
        if ($installment->amount_cents <= 0) {
            throw new RuntimeException('Installment has no amount.');
        }

        return $this->createBookingInvoice(
            $booking,
            $installment->amount_cents,
            "installment_{$installment->sequence}",
            $userId,
            $netDays,
        );
    }

    /**
     * Shared booking-invoice creation. `kind` ('deposit'|'balance'|
     * 'installment_<n>') goes in notes for idempotency. First creation
     * emails the primary contact; idempotent re-runs don't re-email.
     */
    protected function createBookingInvoice(
        Booking $booking,
        int $amountCents,
        string $kind,
        ?int $userId,
        int $netDays,
    ): Invoice {
        [$invoice, $wasCreated] = DB::transaction(function () use ($booking, $amountCents, $kind, $userId, $netDays) {
            $existing = Invoice::query()
                ->where('invoiceable_type', Booking::class)
                ->where('invoiceable_id', $booking->id)
                ->where('notes', $kind)
                ->whereNotIn('status', [
                    InvoiceStatus::Void->value,
                    InvoiceStatus::WrittenOff->value,
                ])
                ->first();

            if ($existing !== null) {
                return [$existing->fresh(), false];
            }

            $invoice = Invoice::query()->create([
                'invoiceable_type' => Booking::class,
                'invoiceable_id' => $booking->id,
                'status' => InvoiceStatus::Issued->value,
                'dunning_stage' => DunningStage::None->value,
                'subtotal_cents' => $amountCents,
                'tax_cents' => 0,
                'total_cents' => $amountCents,
                'paid_cents' => 0,
                'issued_on' => now()->toDateString(),
                'due_on' => now()->addDays($netDays)->toDateString(),
                'sent_at' => now(),
                'net_days' => $netDays,
                'notes' => $kind,
                'issued_by_user_id' => $userId,
            ]);

            // one descriptive line so booking PDFs/statements match the
            // exhibitor-invoice shape; detail carries the event name
            $description = match ($kind) {
                'deposit' => "Booking deposit - {$booking->reference}",
                'balance' => "Booking balance - {$booking->reference}",
                default => "Booking {$booking->reference}",
            };
            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'position' => 0,
                'description' => $description,
                'detail' => $booking->name ?: null,
                'quantity' => 1,
                'unit_price_cents' => $amountCents,
            ]);

            $this->auditLogger->record(
                eventType: 'invoice.issued',
                subject: $invoice,
                payload: [
                    'invoiceable_type' => Booking::class,
                    'invoiceable_id' => $booking->id,
                    'kind' => $kind,
                    'total_cents' => $amountCents,
                    'due_on' => $invoice->due_on?->toDateString(),
                ],
            );

            $this->postIssuanceJournal($invoice, $userId);

            return [$invoice->fresh(), true];
        });

        // email on first creation only; skipped silently when no contact
        // email is on file (the audit row above still records issuance)
        if ($wasCreated) {
            $this->sendIssuedInvoiceEmail($invoice);
        }

        return $invoice;
    }

    /**
     * Dispatch the "your invoice is ready" email. Mirrors
     * sendDunningEmail: looks up the recipient via resolveInvoiceEmail
     * and no-ops when the booking has no contact on file.
     */
    protected function sendIssuedInvoiceEmail(Invoice $invoice): void
    {
        $email = $this->resolveInvoiceEmail($invoice);
        if (empty($email)) {
            return;
        }

        Mail::to($email)->send(new IssuedInvoice($invoice));
    }

    /**
     * Recognize revenue on issuance: debit A/R for the total, credit revenue
     * for the subtotal, credit Sales Tax Payable for the tax. Payment clears
     * A/R; void reverses; write-off moves the remainder to bad debt.
     * Idempotent via `revenue_posted_at`.
     */
    protected function postIssuanceJournal(Invoice $invoice, ?int $userId = null): void
    {
        if ($invoice->revenue_posted_at !== null || $invoice->total_cents <= 0) {
            return;
        }

        $postedOn = $invoice->issued_on?->toDateString() ?? now()->toDateString();
        $arAccount = config('accounting.posting.ar_account', '1100');
        $taxAccount = config('accounting.posting.tax_account', '2200');

        JournalEntry::post([
            'account_code' => $arAccount,
            'description' => "A/R - invoice {$invoice->number}",
            'debit_cents' => $invoice->total_cents,
            'posted_on' => $postedOn,
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'posted_by_user_id' => $userId,
        ]);
        JournalEntry::post([
            'account_code' => $this->revenueAccountFor($invoice),
            'description' => "Revenue recognized - invoice {$invoice->number}",
            'credit_cents' => $invoice->subtotal_cents,
            'posted_on' => $postedOn,
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'posted_by_user_id' => $userId,
        ]);
        if ($invoice->tax_cents > 0) {
            JournalEntry::post([
                'account_code' => $taxAccount,
                'description' => "Sales tax payable - invoice {$invoice->number}",
                'credit_cents' => $invoice->tax_cents,
                'posted_on' => $postedOn,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'posted_by_user_id' => $userId,
            ]);
        }

        $invoice->forceFill(['revenue_posted_at' => now()])->save();
    }

    /**
     * Post the issuance accrual for an already-issued invoice that
     * predates accrual posting. Returns true if it posted, false if the
     * invoice already had revenue posted (or has no total). Used by the
     * `accounting:backfill-issuance` command.
     */
    public function postIssuanceFor(Invoice $invoice, ?int $userId = null): bool
    {
        if ($invoice->revenue_posted_at !== null || $invoice->total_cents <= 0) {
            return false;
        }

        $this->postIssuanceJournal($invoice, $userId);

        return true;
    }

    /**
     * Resolve the revenue account for an invoice from its source type
     * (config/accounting.php -> posting.revenue_accounts).
     */
    protected function revenueAccountFor(Invoice $invoice): string
    {
        $key = match ($invoice->invoiceable_type) {
            ExhibitorOrder::class => 'exhibitor_order',
            Booking::class => 'booking',
            default => 'default',
        };

        $map = config('accounting.posting.revenue_accounts', []);

        return $map[$key] ?? ($map['default'] ?? '4900');
    }

    // Manual-payment recording: works on any invoice, bumps paid_cents
    // directly (bookings have no separate Payment row; exhibitor orders go
    // through OrderPaymentService for full history).

    /**
     * Apply a manual payment directly to an invoice (Booking-sourced;
     * ExhibitorOrder invoices route through OrderPaymentService instead).
     * Posts debit Cash / credit AR inline on the standard 1010/1100 accounts.
     */
    public function applyPaymentToInvoice(
        Invoice $invoice,
        int $amountCents,
        string $method,
        ?string $reference = null,
        ?string $note = null,
        ?int $userId = null,
    ): Invoice {
        if ($amountCents <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }
        if (! $invoice->status->isOpen()) {
            throw new RuntimeException("Cannot apply payment to a {$invoice->status->value} invoice.");
        }

        $applied = min($amountCents, $invoice->balanceCents());

        return DB::transaction(function () use ($invoice, $applied, $method, $reference, $note, $userId) {
            $newPaid = $invoice->paid_cents + $applied;
            $isPaid = $newPaid >= $invoice->total_cents;

            $invoice->update([
                'paid_cents' => $newPaid,
                'status' => $isPaid ? InvoiceStatus::Paid->value : InvoiceStatus::PartialPaid->value,
                'paid_at' => $isPaid && $invoice->paid_at === null ? now() : $invoice->paid_at,
            ]);

            // debit Cash (1010), credit AR (1100); no per-fund coding yet
            $reference ??= 'manual-'.$invoice->number;
            JournalEntry::post([
                'account_code' => '1010',
                'description' => "{$method} payment received - invoice {$invoice->number}".($reference ? " (ref {$reference})" : ''),
                'debit_cents' => $applied,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'posted_by_user_id' => $userId,
            ]);
            JournalEntry::post([
                'account_code' => '1100',
                'description' => "AR cleared by {$method} payment - invoice {$invoice->number}",
                'credit_cents' => $applied,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'posted_by_user_id' => $userId,
            ]);

            $this->auditLogger->record(
                eventType: 'invoice.payment_applied',
                subject: $invoice->fresh(),
                payload: [
                    'amount_cents' => $applied,
                    'method' => $method,
                    'reference' => $reference,
                    'note' => $note,
                    'recorded_by_user_id' => $userId,
                ],
            );

            return $invoice->fresh();
        });
    }

    // Invoice-level refund: for sources with no separate payment-row model
    // (Booking + Client). ExhibitorOrder refunds go through
    // OrderPaymentService against a specific payment.

    /**
     * Refund all or part of an invoice's paid amount: walk paid_cents back,
     * post a reversing pair (debit AR 1100, credit Cash 1010), adjust status.
     * Throws for states that can't be refunded (Draft/Void/WrittenOff/unpaid).
     */
    public function refundInvoice(
        Invoice $invoice,
        int $amountCents,
        ?string $reason = null,
        ?int $userId = null,
    ): Invoice {
        if ($amountCents <= 0) {
            throw new RuntimeException('Refund amount must be positive.');
        }
        if ($invoice->paid_cents <= 0) {
            throw new RuntimeException('Invoice has no payment to refund.');
        }
        if (in_array($invoice->status, [
            InvoiceStatus::Draft,
            InvoiceStatus::Void,
            InvoiceStatus::WrittenOff,
        ], true)) {
            throw new RuntimeException("Cannot refund a {$invoice->status->value} invoice.");
        }

        $refunded = min($amountCents, (int) $invoice->paid_cents);

        $fresh = DB::transaction(function () use ($invoice, $refunded, $reason, $userId) {
            $newPaid = (int) $invoice->paid_cents - $refunded;

            // walk status back to the simplest state that still reflects
            // reality; leave PastDue for the dunning command to re-evaluate
            $newStatus = match (true) {
                $newPaid === 0 => InvoiceStatus::Issued->value,
                $newPaid < $invoice->total_cents => InvoiceStatus::PartialPaid->value,
                default => $invoice->status->value,
            };

            $invoice->update([
                'paid_cents' => $newPaid,
                'status' => $newStatus,
                'paid_at' => $newPaid >= $invoice->total_cents ? $invoice->paid_at : null,
            ]);

            JournalEntry::post([
                'account_code' => '1100',
                'description' => "Refund issued - invoice {$invoice->number}".($reason ? " ({$reason})" : ''),
                'debit_cents' => $refunded,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'posted_by_user_id' => $userId,
            ]);
            JournalEntry::post([
                'account_code' => '1010',
                'description' => "Cash out - refund of invoice {$invoice->number}",
                'credit_cents' => $refunded,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'posted_by_user_id' => $userId,
            ]);

            $this->auditLogger->record(
                eventType: 'invoice.refund_applied',
                subject: $invoice->fresh(),
                payload: [
                    'amount_cents' => $refunded,
                    'reason' => $reason,
                    'refunded_by_user_id' => $userId,
                ],
            );

            return $invoice->fresh();
        });

        $this->sendRefundNotice($fresh, $refunded, $reason);

        return $fresh;
    }

    // Write-off: move an uncollectable balance off AR as bad debt. Terminal.

    /**
     * Write off the outstanding balance as bad debt: debit Expense (5900),
     * credit AR (1100). Refuses paid/void/already-written-off invoices.
     */
    public function writeOff(Invoice $invoice, string $reason, ?int $userId = null): Invoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new RuntimeException('Cannot write off a paid invoice.');
        }
        if ($invoice->status === InvoiceStatus::Void) {
            throw new RuntimeException('Cannot write off a void invoice.');
        }
        if ($invoice->status === InvoiceStatus::WrittenOff) {
            return $invoice;
        }

        $balance = $invoice->balanceCents();
        if ($balance <= 0) {
            throw new RuntimeException('Invoice has no balance to write off.');
        }

        return DB::transaction(function () use ($invoice, $balance, $reason, $userId) {
            $invoice->update([
                'status' => InvoiceStatus::WrittenOff->value,
                'void_reason' => $reason,
                'voided_at' => now(),
            ]);

            // debit Bad Debt Expense (5900), credit AR; 5900 must exist in
            // the chart of accounts or the post throws (intended)
            JournalEntry::post([
                'account_code' => '5900',
                'description' => "Bad debt write-off - invoice {$invoice->number} ({$reason})",
                'debit_cents' => $balance,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'posted_by_user_id' => $userId,
            ]);
            JournalEntry::post([
                'account_code' => '1100',
                'description' => "AR written off - invoice {$invoice->number}",
                'credit_cents' => $balance,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'posted_by_user_id' => $userId,
            ]);

            $this->auditLogger->record(
                eventType: 'invoice.written_off',
                subject: $invoice->fresh(),
                payload: [
                    'balance_written_off_cents' => $balance,
                    'reason' => $reason,
                    'written_off_by_user_id' => $userId,
                ],
            );

            return $invoice->fresh();
        });
    }
}
