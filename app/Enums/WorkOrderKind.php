<?php

namespace App\Enums;

enum WorkOrderKind: string
{
    case Setup = 'setup';
    case Teardown = 'teardown';
    case PreventiveMaintenance = 'preventive_maintenance';
    case Repair = 'repair';
    case EventSupport = 'event_support';
    case InventoryReplenishment = 'inventory_replenishment';
    case Cleaning = 'cleaning';
}
