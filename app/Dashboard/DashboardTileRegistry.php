<?php

namespace App\Dashboard;

use App\Services\SystemSettings\SystemSettings;

/** Catalog of available dashboard tiles. */
class DashboardTileRegistry
{
    /**
     * Built-in default layout (display order). Mirrors the string default
     * of the `defaults.dashboard_default_tiles` setting.
     *
     * @var list<string>
     */
    public const BUILTIN_DEFAULT_TILES = [
        'quick_links',
        'kpi_strip',
        'needs_attention',
        'revenue_trend',
        'pipeline_by_stage',
        'bookings_by_status',
        'today_outline',
    ];

    /** @var array<string, DashboardTile> */
    protected array $tiles = [];

    public function register(DashboardTile $tile): void
    {
        $this->tiles[$tile->key()] = $tile;
    }

    public function get(string $key): ?DashboardTile
    {
        return $this->tiles[$key] ?? null;
    }

    /** @return array<string, DashboardTile> */
    public function all(): array
    {
        return $this->tiles;
    }

    /**
     * Default tiles: the admin override (comma-separated
     * `defaults.dashboard_default_tiles`) filtered to existing tiles,
     * else the built-in layout.
     *
     * @return list<string>
     */
    public function defaultTileKeys(): array
    {
        $configured = (string) app(SystemSettings::class)
            ->get('defaults.dashboard_default_tiles', '');

        $keys = array_values(array_filter(
            array_map('trim', explode(',', $configured)),
            fn (string $key): bool => $key !== '' && isset($this->tiles[$key]),
        ));

        if ($keys !== []) {
            return $keys;
        }

        return array_values(array_filter(
            self::BUILTIN_DEFAULT_TILES,
            fn (string $key): bool => isset($this->tiles[$key]),
        ));
    }
}
