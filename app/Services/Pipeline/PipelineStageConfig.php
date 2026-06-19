<?php

namespace App\Services\Pipeline;

use App\Enums\LeadStage;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Effective pipeline-stage label + probability: admin overrides layered
 * over enum defaults. Overrides are one JSON blob under the
 * `pipeline.stages` setting key (written directly, not via the settings
 * registry, so it stays out of the flat list and gets its own editor).
 * Terminal stages (Won/Lost) keep their fixed 100% / 0% regardless of
 * any stored value.
 */
class PipelineStageConfig
{
    protected const SETTING_KEY = 'pipeline.stages';

    protected const CACHE_KEY = 'pipeline.stages.v1';

    public function label(LeadStage $stage): string
    {
        $stored = $this->stored()[$stage->value]['label'] ?? null;

        return is_string($stored) && $stored !== ''
            ? $stored
            : $this->defaultLabel($stage);
    }

    public function probability(LeadStage $stage): float
    {
        // terminal stages are fixed - Won 100%, Lost 0%
        if ($stage->isTerminal()) {
            return $stage->defaultProbability();
        }

        $stored = $this->stored()[$stage->value]['probability'] ?? null;

        return is_numeric($stored)
            ? max(0.0, min(1.0, (float) $stored))
            : $stage->defaultProbability();
    }

    /**
     * Every stage's effective config, in enum order.
     *
     * @return list<array{value: string, label: string, probability: float, is_terminal: bool}>
     */
    public function all(): array
    {
        return array_map(fn (LeadStage $stage) => [
            'value' => $stage->value,
            'label' => $this->label($stage),
            'probability' => $this->probability($stage),
            'is_terminal' => $stage->isTerminal(),
        ], LeadStage::cases());
    }

    /**
     * Persist label + probability overrides. Terminal probabilities are
     * ignored. Input is raw request data, so each field is re-validated.
     *
     * @param  array<string, array<string, mixed>>  $input
     */
    public function save(array $input, ?int $userId = null): void
    {
        $payload = [];
        foreach (LeadStage::cases() as $stage) {
            $row = $input[$stage->value] ?? [];
            $entry = [];

            if (isset($row['label']) && is_string($row['label']) && trim($row['label']) !== '') {
                $entry['label'] = trim($row['label']);
            }
            if (! $stage->isTerminal() && isset($row['probability']) && is_numeric($row['probability'])) {
                $entry['probability'] = max(0.0, min(1.0, (float) $row['probability']));
            }

            if ($entry !== []) {
                $payload[$stage->value] = $entry;
            }
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'value' => json_encode($payload),
                'is_secret' => false,
                'updated_by_user_id' => $userId,
            ],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Decoded override map, cached forever (invalidated on save).
     *
     * @return array<string, array{label?: string, probability?: float}>
     */
    protected function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $raw = SystemSetting::query()
                ->where('key', self::SETTING_KEY)
                ->value('value');

            if (! is_string($raw) || $raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        });
    }

    protected function defaultLabel(LeadStage $stage): string
    {
        return match ($stage) {
            LeadStage::New => 'New',
            LeadStage::Qualified => 'Qualified',
            LeadStage::ProposalSent => 'Proposal sent',
            LeadStage::ContractSent => 'Contract sent',
            LeadStage::Won => 'Won',
            LeadStage::Lost => 'Lost',
        };
    }
}
