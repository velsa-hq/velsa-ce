<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Reads composer.lock + package-lock.json into a runtime dependency tree
 * (name, version, SPDX license, homepage) for the /licenses attribution page.
 *
 * In-process memoization only, no cache layer: serializing a Collection into
 * Laravel's cache under classmap-authoritative autoloading yields
 * __PHP_Incomplete_Class on the next request.
 */
class LicenseRegistry
{
    protected ?Collection $phpCache = null;

    protected ?Collection $jsCache = null;

    public function php(): Collection
    {
        if ($this->phpCache !== null) {
            return $this->phpCache;
        }

        $lockPath = base_path('composer.lock');

        if (! File::exists($lockPath)) {
            return $this->phpCache = collect();
        }

        $lock = json_decode(File::get($lockPath), true);

        return $this->phpCache = collect($lock['packages'] ?? [])
            ->map(fn (array $p): array => [
                'name' => $p['name'],
                'version' => $p['version'],
                'license' => $this->normalizeLicense($p['license'] ?? []),
                'homepage' => $p['homepage'] ?? $p['source']['url'] ?? null,
                'description' => $p['description'] ?? null,
            ])
            ->sortBy('name')
            ->values();
    }

    public function js(): Collection
    {
        if ($this->jsCache !== null) {
            return $this->jsCache;
        }

        $lockPath = base_path('package-lock.json');

        if (! File::exists($lockPath)) {
            return $this->jsCache = collect();
        }

        $lock = json_decode(File::get($lockPath), true);

        return $this->jsCache = collect($lock['packages'] ?? [])
            ->filter(fn (array $p, string $path): bool => $path !== ''
                && ! ($p['dev'] ?? false)
                && ! ($p['link'] ?? false))
            ->map(fn (array $p, string $path): array => [
                'name' => $p['name'] ?? $this->nameFromPath($path),
                'version' => $p['version'] ?? 'unknown',
                'license' => $this->normalizeJsLicense($p['license'] ?? null),
                'homepage' => $p['homepage'] ?? $p['repository']['url'] ?? null,
                'description' => $p['description'] ?? null,
            ])
            ->unique(fn (array $p): string => $p['name'].'@'.$p['version'])
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  array<int, string>|string  $license
     */
    protected function normalizeLicense(array|string $license): string
    {
        if (is_string($license)) {
            return $license !== '' ? $license : 'UNKNOWN';
        }

        if (count($license) === 0) {
            return 'UNKNOWN';
        }

        // Composer represents "user picks one" as an array; render as SPDX OR
        return count($license) === 1
            ? $license[0]
            : '('.implode(' OR ', $license).')';
    }

    /**
     * @param  array<string,string>|string|null  $license
     */
    protected function normalizeJsLicense(array|string|null $license): string
    {
        if (is_string($license) && $license !== '') {
            return $license;
        }

        if (is_array($license) && isset($license['type'])) {
            return $license['type'];
        }

        return 'UNKNOWN';
    }

    protected function nameFromPath(string $path): string
    {
        return str(str_replace('\\', '/', $path))
            ->afterLast('node_modules/')
            ->value();
    }
}
