<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\PaymentSchedule;
use App\Services\Accounting\PaymentScheduleService;
use Illuminate\Database\Seeder;

/**
 * Attaches a 3-installment payment schedule to the two largest
 * Definite/Completed bookings. One lands fully in the past so
 * IssueDueInstallments fires invoices on the next run; the other is
 * mixed past/future for a varied Paid / Invoiced / Pending view.
 * No-ops when no candidate exists. Idempotent.
 */
class PaymentSchedulesSeeder extends Seeder
{
    public function run(PaymentScheduleService $service): void
    {
        if (PaymentSchedule::query()->exists()) {
            $this->command->info('PaymentSchedulesSeeder: schedules already exist, skipping.');

            return;
        }

        // deposit_percent 0 unlocks the schedule path
        $candidates = Booking::query()
            ->whereIn('status', [
                BookingStatus::Definite->value,
                BookingStatus::Completed->value,
            ])
            ->where('total_cents', '>', 1_000_000)
            ->orderByDesc('total_cents')
            ->limit(2)
            ->get();

        $applied = 0;
        foreach ($candidates as $idx => $booking) {
            $booking->update(['deposit_percent' => 0]);

            $third = (int) round($booking->total_cents / 3);
            $remainder = $booking->total_cents - 2 * $third;

            if ($idx === 0) {
                // all three in the past so the daily command issues invoices next run
                $service->replaceInstallments($booking, [
                    ['sequence' => 1, 'due_date' => now()->subDays(120)->toDateString(), 'amount_cents' => $third, 'label' => 'Deposit (1/3)'],
                    ['sequence' => 2, 'due_date' => now()->subDays(60)->toDateString(), 'amount_cents' => $third, 'label' => 'Mid (2/3)'],
                    ['sequence' => 3, 'due_date' => now()->subDays(7)->toDateString(), 'amount_cents' => $remainder, 'label' => 'Final balance'],
                ]);
            } else {
                // one overdue, one upcoming, one far-out
                $service->replaceInstallments($booking, [
                    ['sequence' => 1, 'due_date' => now()->subDays(14)->toDateString(), 'amount_cents' => $third, 'label' => 'Deposit (1/3)'],
                    ['sequence' => 2, 'due_date' => now()->addDays(30)->toDateString(), 'amount_cents' => $third, 'label' => 'Mid (2/3)'],
                    ['sequence' => 3, 'due_date' => now()->addDays(90)->toDateString(), 'amount_cents' => $remainder, 'label' => 'Final balance'],
                ]);
            }

            $applied++;
        }

        $this->command->info("PaymentSchedulesSeeder: applied to {$applied} booking(s).");
    }
}
