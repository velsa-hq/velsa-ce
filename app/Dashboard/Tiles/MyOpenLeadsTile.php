<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Models\Lead;
use App\Models\User;

class MyOpenLeadsTile extends DashboardTile
{
    public function key(): string
    {
        return 'my_open_leads';
    }

    public function label(): string
    {
        return 'My open leads';
    }

    public function description(): string
    {
        return 'Leads currently assigned to you, ordered by expected close date. Sales-rep view of your personal pipeline.';
    }

    public function columnSpan(): int
    {
        return 6;
    }

    public function permission(): ?string
    {
        return 'leads.view';
    }

    public function render(User $user): array
    {
        $leads = Lead::query()
            ->open()
            ->forOwner($user->id)
            ->with(['client:id,name'])
            ->orderBy('expected_close_date')
            ->limit(10)
            ->get()
            ->map(fn (Lead $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'stage' => $l->stage?->value,
                'stage_label' => $l->stage?->value
                    ? ucfirst(str_replace('_', ' ', $l->stage->value))
                    : null,
                'client_name' => $l->client?->name,
                'estimated_value_cents' => (int) $l->estimated_value_cents,
                'weighted_value_cents' => $l->weightedValueCents(),
                'expected_close_at' => $l->expected_close_date?->toDateString(),
            ])
            ->all();

        return [
            'leads' => $leads,
            'total_weighted_cents' => array_sum(array_column($leads, 'weighted_value_cents')),
        ];
    }
}
