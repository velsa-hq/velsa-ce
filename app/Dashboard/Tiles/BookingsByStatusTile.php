<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

class BookingsByStatusTile extends DashboardTile
{
    public function key(): string
    {
        return 'bookings_by_status';
    }

    public function label(): string
    {
        return 'Bookings by status';
    }

    public function description(): string
    {
        return 'Distribution of bookings starting in the next 60 days (and recent 30), grouped by status.';
    }

    public function columnSpan(): int
    {
        return 4;
    }

    public function permission(): ?string
    {
        return 'bookings.view';
    }

    public function render(User $user): array
    {
        $rows = Booking::query()
            ->selectRaw('status, count(*) as count')
            ->where('start_at', '>=', now()->subDays(30))
            ->where('start_at', '<=', now()->addDays(60))
            ->groupBy('status')
            ->pluck('count', 'status');

        $out = [];
        foreach (BookingStatus::cases() as $status) {
            $out[] = [
                'status' => $status->value,
                'label' => ucfirst(str_replace('_', ' ', $status->value)),
                'count' => (int) ($rows[$status->value] ?? 0),
            ];
        }

        return ['statuses' => $out];
    }
}
