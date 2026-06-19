<?php

namespace App\Services\Accounting;

use App\Models\Booking;
use App\Models\Installment;
use App\Models\PaymentSchedule;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Manages a Booking's PaymentSchedule + Installments.
 *
 * Invoiced installments are immutable; void the invoice (clearing
 * invoice_id) to change them. Mutually exclusive with Booking's
 * deposit_percent flow - the controller enforces that gate.
 */
class PaymentScheduleService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * Replace the schedule. Un-invoiced installments missing from the
     * list are dropped; invoiced ones are left frozen.
     *
     * @param  list<array{sequence:int,due_date:string,amount_cents:int,label?:?string}>  $installments
     */
    public function replaceInstallments(Booking $booking, array $installments, ?int $userId = null): PaymentSchedule
    {
        return DB::transaction(function () use ($booking, $installments, $userId) {
            $schedule = PaymentSchedule::query()->firstOrCreate(
                ['booking_id' => $booking->id],
                ['total_cents' => 0, 'activated_at' => now()],
            );

            $existing = $schedule->installments()->get()->keyBy('sequence');
            $incomingSequences = collect($installments)->pluck('sequence');

            // refuse to drop an already-invoiced installment
            foreach ($existing as $seq => $row) {
                if ($row->isInvoiced() && ! $incomingSequences->contains($seq)) {
                    throw new RuntimeException(
                        "Cannot remove installment #{$seq} - it has already been invoiced. Void the invoice first."
                    );
                }
            }

            foreach ($installments as $entry) {
                $seq = $entry['sequence'];
                $row = $existing->get($seq);

                if ($row !== null && $row->isInvoiced()) {
                    // frozen once invoiced
                    continue;
                }

                Installment::query()->updateOrCreate(
                    [
                        'payment_schedule_id' => $schedule->id,
                        'sequence' => $seq,
                    ],
                    [
                        'due_date' => $entry['due_date'],
                        'amount_cents' => $entry['amount_cents'],
                        'label' => $entry['label'] ?? null,
                    ],
                );
            }

            // drop un-invoiced installments not in the incoming set
            $schedule->installments()
                ->whereNotIn('sequence', $incomingSequences)
                ->whereNull('invoice_id')
                ->delete();

            $schedule->refresh();
            $schedule->update([
                'total_cents' => (int) $schedule->installments()->sum('amount_cents'),
            ]);

            $this->auditLogger->record(
                eventType: 'payment_schedule.replaced',
                subject: $schedule,
                payload: [
                    'booking_id' => $booking->id,
                    'installment_count' => $schedule->installments()->count(),
                    'total_cents' => $schedule->total_cents,
                    'actor_user_id' => $userId,
                ],
            );

            return $schedule->fresh(['installments']);
        });
    }

    /**
     * Hard-delete the schedule + un-invoiced installments. Refuses if
     * any installment has been invoiced.
     */
    public function delete(PaymentSchedule $schedule, ?int $userId = null): void
    {
        DB::transaction(function () use ($schedule, $userId) {
            $invoiced = $schedule->installments()->whereNotNull('invoice_id')->count();
            if ($invoiced > 0) {
                throw new RuntimeException(
                    "Cannot delete schedule - {$invoiced} installment(s) already invoiced."
                );
            }

            $bookingId = $schedule->booking_id;
            $schedule->installments()->delete();
            $schedule->delete();

            $this->auditLogger->record(
                eventType: 'payment_schedule.deleted',
                subject: $schedule,
                payload: [
                    'booking_id' => $bookingId,
                    'actor_user_id' => $userId,
                ],
            );
        });
    }
}
