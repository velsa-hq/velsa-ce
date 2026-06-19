<?php

namespace App\Reports\Handlers;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\Venue;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use Illuminate\Support\Carbon;

/**
 * Leads grouped by stage, flattened for export. Weighted dollars use stage probability.
 */
class SalesPipelineReport implements ReportHandler
{
    public function slug(): string
    {
        return 'sales-pipeline';
    }

    public function category(): string
    {
        return 'Sales';
    }

    public function title(): string
    {
        return 'Sales pipeline & forecast';
    }

    public function description(): string
    {
        return 'Open and closed leads grouped by stage, with weighted forecast dollars (estimated value x probability) per stage and overall.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'from', 'label' => 'Expected close from', 'type' => 'date'],
            ['key' => 'to', 'label' => 'Expected close to', 'type' => 'date'],
            ['key' => 'venue_id', 'label' => 'Venue', 'type' => 'select', 'options' => $this->venueOptions()],
        ];
    }

    public function run(array $params): ReportResult
    {
        // all filters optional; date window applies to expected_close_date
        $from = isset($params['from']) && $params['from'] !== '' ? Carbon::parse($params['from'])->startOfDay() : null;
        $to = isset($params['to']) && $params['to'] !== '' ? Carbon::parse($params['to'])->endOfDay() : null;
        $venueId = isset($params['venue_id']) && $params['venue_id'] !== '' ? (int) $params['venue_id'] : null;

        $leads = Lead::query()
            ->with(['client:id,name', 'venue:id,name', 'owner:id,name,email'])
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->when($from, fn ($q, $d) => $q->where('expected_close_date', '>=', $d))
            ->when($to, fn ($q, $d) => $q->where('expected_close_date', '<=', $d))
            ->get();

        $rows = $leads->map(fn (Lead $l) => [
            'stage' => $l->stage?->value,
            'name' => $l->name,
            'client' => $l->client?->name ?? '-',
            'venue' => $l->venue?->name ?? '-',
            'owner' => $l->owner?->email ?? '-',
            'estimated_dollars' => number_format($l->estimated_value_cents / 100, 2),
            'probability' => round(($l->probability ?? 0) * 100).'%',
            'weighted_dollars' => number_format($l->weightedValueCents() / 100, 2),
            'expected_close' => $l->expected_close_date?->toDateString() ?? '-',
        ])->all();

        $openLeads = $leads->filter(fn (Lead $l) => $l->stage?->isOpen() === true);
        $summary = [
            ['label' => 'Open leads', 'value' => (string) $openLeads->count()],
            ['label' => 'Total open value', 'value' => '$'.number_format($openLeads->sum('estimated_value_cents') / 100, 0)],
            ['label' => 'Weighted forecast', 'value' => '$'.number_format($openLeads->sum(fn (Lead $l) => $l->weightedValueCents()) / 100, 0)],
        ];

        foreach (LeadStage::cases() as $stage) {
            $stageLeads = $leads->filter(fn (Lead $l) => $l->stage === $stage);
            if ($stageLeads->isNotEmpty()) {
                $summary[] = [
                    'label' => ucfirst(str_replace('_', ' ', $stage->value)),
                    'value' => $stageLeads->count().' · $'.number_format($stageLeads->sum(fn (Lead $l) => $l->weightedValueCents()) / 100, 0),
                ];
            }
        }

        return new ReportResult(
            title: $this->title(),
            description: 'All leads currently in the pipeline plus terminal stages',
            columns: [
                ['key' => 'stage', 'label' => 'Stage'],
                ['key' => 'name', 'label' => 'Lead'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'owner', 'label' => 'Owner'],
                ['key' => 'estimated_dollars', 'label' => 'Est. $', 'align' => 'right'],
                ['key' => 'probability', 'label' => 'Prob.', 'align' => 'right'],
                ['key' => 'weighted_dollars', 'label' => 'Weighted $', 'align' => 'right'],
                ['key' => 'expected_close', 'label' => 'Close'],
            ],
            rows: $rows,
            summary: $summary,
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    protected function venueOptions(): array
    {
        return Venue::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Venue $v) => ['value' => (int) $v->id, 'label' => $v->name])
            ->all();
    }
}
