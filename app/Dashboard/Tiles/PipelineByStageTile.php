<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;

class PipelineByStageTile extends DashboardTile
{
    public function key(): string
    {
        return 'pipeline_by_stage';
    }

    public function label(): string
    {
        return 'Pipeline by stage';
    }

    public function description(): string
    {
        return 'Bar chart of open leads grouped by stage, with weighted forecast value per stage.';
    }

    public function columnSpan(): int
    {
        return 4;
    }

    public function permission(): ?string
    {
        return 'pipeline.view';
    }

    public function render(User $user): array
    {
        $leads = Lead::query()->open()->get();
        $byStage = [];
        foreach (LeadStage::cases() as $stage) {
            if (! $stage->isOpen()) {
                continue;
            }
            $stageLeads = $leads->filter(fn (Lead $l) => $l->stage === $stage);
            $byStage[] = [
                'stage' => $stage->value,
                'label' => ucfirst(str_replace('_', ' ', $stage->value)),
                'count' => $stageLeads->count(),
                'weighted_cents' => (int) $stageLeads->sum(fn (Lead $l) => $l->weightedValueCents()),
            ];
        }

        return ['stages' => $byStage];
    }
}
