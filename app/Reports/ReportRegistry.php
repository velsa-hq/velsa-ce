<?php

namespace App\Reports;

use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Catalog of named reports, populated in AppServiceProvider::boot().
 */
class ReportRegistry
{
    /**
     * @var array<string, ReportHandler>
     */
    protected array $handlers = [];

    public function register(ReportHandler $handler): void
    {
        $this->handlers[$handler->slug()] = $handler;
    }

    public function get(string $slug): ReportHandler
    {
        if (! isset($this->handlers[$slug])) {
            throw new RuntimeException("Unknown report: {$slug}");
        }

        return $this->handlers[$slug];
    }

    public function has(string $slug): bool
    {
        return isset($this->handlers[$slug]);
    }

    /**
     * @return Collection<int, ReportHandler>
     */
    public function all(): Collection
    {
        return collect(array_values($this->handlers));
    }

    /**
     * @return array<string, array<int, ReportHandler>>
     */
    public function grouped(): array
    {
        return $this->all()
            ->groupBy(fn (ReportHandler $h) => $h->category())
            ->map(fn ($group) => $group->all())
            ->all();
    }
}
