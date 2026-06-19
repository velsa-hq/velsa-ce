<?php

namespace App\Services\Import;

use InvalidArgumentException;

/**
 * Resolves the registered importers (config/import.php) by key. Built once
 * and shared; importers are stateless.
 */
class ImportRegistry
{
    /** @var array<string, Importer> */
    protected array $importers = [];

    /** @var array<string, class-string<Importer>> */
    protected array $classes = [];

    /**
     * @param  list<class-string<Importer>>  $classes
     */
    public function __construct(array $classes)
    {
        foreach ($classes as $class) {
            $importer = app($class);

            if (! $importer instanceof Importer) {
                throw new InvalidArgumentException("{$class} must implement ".Importer::class);
            }

            $this->importers[$importer->key()] = $importer;
            $this->classes[$importer->key()] = $class;
        }
    }

    /**
     * A fresh importer instance for a single import run. Importers may carry
     * per-run state (e.g. the chart-of-accounts importer tracks codes seen so
     * far to resolve parents and catch duplicates), so a run must not reuse
     * the cached metadata instance.
     */
    public function fresh(string $key): Importer
    {
        return app($this->classes[$key] ?? throw new InvalidArgumentException("Unknown import kind: {$key}"));
    }

    /**
     * @return list<Importer>
     */
    public function all(): array
    {
        return array_values($this->importers);
    }

    public function has(string $key): bool
    {
        return isset($this->importers[$key]);
    }

    public function get(string $key): ?Importer
    {
        return $this->importers[$key] ?? null;
    }

    /**
     * Resolve an importer or fail - for routes that have already validated
     * the kind exists.
     */
    public function getOrFail(string $key): Importer
    {
        return $this->get($key) ?? throw new InvalidArgumentException("Unknown import kind: {$key}");
    }
}
