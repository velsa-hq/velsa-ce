<?php

namespace App\Dashboard;

use App\Models\User;

/**
 * A single dashboard widget. Subclasses are registered in the catalog
 * and rendered per-user per-request.
 */
abstract class DashboardTile
{
    /** Stable identifier, used in user preferences and frontend lookups. */
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /** Column span on a 12-column grid. */
    public function columnSpan(): int
    {
        return 6;
    }

    /** React component key; defaults to key() so tiles can share one widget. */
    public function component(): string
    {
        return $this->key();
    }

    /** Permission required (at any venue) to see this tile; null = everyone. */
    public function permission(): ?string
    {
        return null;
    }

    /** @return array<string, mixed> */
    abstract public function render(User $user): array;
}
