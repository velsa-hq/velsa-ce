<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

class MyUpcomingBookingsTile extends DashboardTile
{
    public function key(): string
    {
        return 'my_upcoming_bookings';
    }

    public function label(): string
    {
        return 'My upcoming bookings';
    }

    public function description(): string
    {
        return 'Bookings you own (or are assigned to) starting in the next 14 days. Sales / ops view of your near-term events.';
    }

    public function columnSpan(): int
    {
        return 6;
    }

    public function permission(): ?string
    {
        return 'bookings.view';
    }

    public function render(User $user): array
    {
        $bookings = Booking::query()
            ->where('owner_user_id', $user->id)
            ->whereIn('status', [
                BookingStatus::Hold->value,
                BookingStatus::Tentative->value,
                BookingStatus::Definite->value,
            ])
            ->whereBetween('start_at', [now()->startOfDay(), now()->addDays(14)])
            ->with(['client:id,name', 'venue:id,name,slug'])
            ->orderBy('start_at')
            ->limit(10)
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'reference' => $b->reference,
                'name' => $b->name,
                'status' => $b->status?->value,
                'client_name' => $b->client?->name,
                'venue_name' => $b->venue?->name,
                'start_at' => $b->start_at?->toIso8601String(),
                'end_at' => $b->end_at?->toIso8601String(),
            ])
            ->all();

        return ['bookings' => $bookings];
    }
}
