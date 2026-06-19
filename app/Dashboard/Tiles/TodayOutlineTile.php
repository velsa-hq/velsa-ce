<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Models\OutlineItem;
use App\Models\User;
use App\Support\DateFormatter;

class TodayOutlineTile extends DashboardTile
{
    public function key(): string
    {
        return 'today_outline';
    }

    public function label(): string
    {
        return 'Today\'s outline items';
    }

    public function description(): string
    {
        return 'Run-of-show items scheduled for today across all events, ordered by time. Capped at 15 entries.';
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
        $items = OutlineItem::query()
            ->with(['outline.booking:id,reference,name', 'departmentRef:key,label,color'])
            ->between(now()->startOfDay(), now()->endOfDay()->addSecond())
            ->orderBy('scheduled_at')
            ->limit(15)
            ->get()
            ->map(fn (OutlineItem $i) => [
                'id' => $i->id,
                'time' => DateFormatter::timeOnly($i->scheduled_at),
                'duration' => $i->duration_minutes,
                'title' => $i->title,
                'department' => $i->department,
                'department_label' => $i->departmentLabel(),
                'booking_name' => $i->outline?->booking?->name,
                'booking_id' => $i->outline?->booking?->id,
            ])
            ->all();

        return ['items' => $items];
    }
}
