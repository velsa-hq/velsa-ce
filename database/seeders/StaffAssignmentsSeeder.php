<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Rosters a small staff team onto upcoming bookings so the Staff card and
 * responsible-user picker are populated out of the box. Picks whatever
 * bookings/users exist and skips quietly otherwise. Idempotent: skips if
 * any StaffAssignment row already exists.
 */
class StaffAssignmentsSeeder extends Seeder
{
    public function run(): void
    {
        if (StaffAssignment::query()->exists()) {
            $this->command->info('StaffAssignmentsSeeder: assignments already present, skipping.');

            return;
        }

        // typical event-day shifts; rate in cents (3500 = $35/hr)
        $template = [
            ['role' => 'Event lead', 'rate_cents' => 5500],
            ['role' => 'AV technician', 'rate_cents' => 4500],
            ['role' => 'Catering captain', 'rate_cents' => 4000],
            ['role' => 'Setup crew', 'rate_cents' => 2800],
            ['role' => 'Reception desk', 'rate_cents' => 2500],
        ];

        // prefer the ops-team users, fall back to any active user
        $users = User::query()
            ->whereIn('email', [
                'carlos.mendez@sentinelbay.ca.gov',
                'priya.shah@sentinelbay.ca.gov',
                'marcus.holloway@sentinelbay.ca.gov',
                'lin.zhao@sentinelbay.ca.gov',
                'maya.chen@sentinelbay.ca.gov',
            ])
            ->get();

        if ($users->count() < count($template)) {
            // pad so each shift gets a body
            $extra = User::query()
                ->whereNotIn('id', $users->pluck('id'))
                ->limit(count($template) - $users->count())
                ->get();
            $users = $users->concat($extra);
        }

        if ($users->isEmpty()) {
            $this->command->warn('StaffAssignmentsSeeder: no users found. Skipping.');

            return;
        }

        $bookings = Booking::query()
            ->whereIn('status', [
                BookingStatus::Definite->value,
                BookingStatus::Tentative->value,
            ])
            ->whereBetween('start_at', [now()->subDays(7), now()->addDays(60)])
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->orderBy('start_at')
            ->limit(5)
            ->get();

        $applied = 0;
        foreach ($bookings as $booking) {
            foreach ($template as $idx => $row) {
                $user = $users[$idx % $users->count()];
                StaffAssignment::query()->create([
                    'booking_id' => $booking->id,
                    'user_id' => $user->id,
                    'role' => $row['role'],
                    'start_at' => $booking->start_at->copy()->subHours(2),
                    'end_at' => $booking->end_at->copy()->addHour(),
                    'hourly_rate_cents' => $row['rate_cents'],
                ]);
                $applied++;
            }
        }

        $this->command->info("StaffAssignmentsSeeder: rostered {$applied} shift(s) across {$bookings->count()} booking(s).");
    }
}
