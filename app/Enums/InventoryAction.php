<?php

namespace App\Enums;

/**
 * Inventory delta semantics on a WorkOrderItem.
 *
 * - Deploy: temporarily moves inventory OUT (quantity_available decreases)
 * - Return: inventory comes back IN (quantity_available increases)
 * - Consume: permanently removes from total stock
 * - Replace: swap broken-in for fresh-out (no net change unless quantity differs)
 */
enum InventoryAction: string
{
    case Deploy = 'deploy';
    case Return = 'return';
    case Consume = 'consume';
    case Replace = 'replace';

    public function deltaAvailable(int $quantity): int
    {
        return match ($this) {
            self::Deploy => -$quantity,
            self::Return => +$quantity,
            self::Consume => -$quantity,
            self::Replace => 0,
        };
    }

    public function deltaTotal(int $quantity): int
    {
        return match ($this) {
            self::Consume => -$quantity,
            default => 0,
        };
    }
}
