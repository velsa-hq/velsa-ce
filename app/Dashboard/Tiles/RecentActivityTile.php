<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Models\AuditEvent;
use App\Models\User;

class RecentActivityTile extends DashboardTile
{
    public function key(): string
    {
        return 'recent_activity';
    }

    public function label(): string
    {
        return 'Recent activity';
    }

    public function description(): string
    {
        return 'Last 10 audit-log entries - who did what across the system.';
    }

    public function columnSpan(): int
    {
        return 8;
    }

    public function permission(): ?string
    {
        return 'audit.view';
    }

    public function render(User $user): array
    {
        $entries = AuditEvent::query()
            ->with(['user:id,name,email'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AuditEvent $e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'created_at' => $e->created_at?->toIso8601String(),
                'user_email' => $e->user?->email,
                'subject_type' => $e->subject_type ? class_basename($e->subject_type) : null,
                'subject_id' => $e->subject_id,
            ])
            ->all();

        return ['entries' => $entries];
    }
}
