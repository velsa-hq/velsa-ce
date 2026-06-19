<?php

namespace App\Reports\Handlers;

use App\Models\ReportDefinition;
use App\Reports\AdHocReportRunner;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;

/**
 * Adapts a stored ReportDefinition to ReportHandler; one instance per definition.
 */
class UserDefinedReportHandler implements ReportHandler
{
    public function __construct(
        protected ReportDefinition $definition,
        protected AdHocReportRunner $runner,
    ) {}

    public function slug(): string
    {
        return 'custom-'.$this->definition->slug;
    }

    public function category(): string
    {
        return 'Custom';
    }

    public function title(): string
    {
        return $this->definition->name;
    }

    public function description(): string
    {
        return $this->definition->description ?? '';
    }

    /**
     * No parameter schema; filter config is embedded in the definition.
     */
    public function parameters(): array
    {
        return [];
    }

    public function run(array $params): ReportResult
    {
        return $this->runner->run($this->definition);
    }
}
