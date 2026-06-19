<?php

namespace App\Enums;

/** Governmental fund types per GASB classification. */
enum FundType: string
{
    case General = 'general';
    case SpecialRevenue = 'special_revenue';
    case Enterprise = 'enterprise';
    case CapitalProjects = 'capital_projects';
    case DebtService = 'debt_service';
    case InternalService = 'internal_service';
    case Fiduciary = 'fiduciary';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General Fund',
            self::SpecialRevenue => 'Special Revenue Fund',
            self::Enterprise => 'Enterprise Fund',
            self::CapitalProjects => 'Capital Projects Fund',
            self::DebtService => 'Debt Service Fund',
            self::InternalService => 'Internal Service Fund',
            self::Fiduciary => 'Fiduciary Fund',
        };
    }
}
