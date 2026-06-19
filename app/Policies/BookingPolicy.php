<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Policies\Concerns\ChecksVenueAccess;

/**
 * Booking authorization. approve / hold_release gate their own controllers.
 * NIST AC-3/AC-6.
 */
class BookingPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasVenuePermission('bookings.view');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $this->permits($user, 'bookings.view', $booking->venue_id);
    }

    public function create(User $user): bool
    {
        return $user->hasVenuePermission('bookings.create');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $this->permits($user, 'bookings.edit', $booking->venue_id);
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $this->permits($user, 'bookings.delete', $booking->venue_id);
    }
}
