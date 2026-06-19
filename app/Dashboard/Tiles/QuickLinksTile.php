<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Models\User;

class QuickLinksTile extends DashboardTile
{
    public function key(): string
    {
        return 'quick_links';
    }

    public function label(): string
    {
        return 'Quick links';
    }

    public function description(): string
    {
        return 'Clickable chiclets for jumping to top-level sections - Accounting, Contracts, Funds, and the rest.';
    }

    public function columnSpan(): int
    {
        return 12;
    }

    public function render(User $user): array
    {
        $prefs = $user->dashboard_preferences;
        $selectedKeys = [];
        if (is_array($prefs) && isset($prefs['quick_link_keys']) && is_array($prefs['quick_link_keys'])) {
            $selectedKeys = array_values(array_filter(
                $prefs['quick_link_keys'],
                fn ($key) => is_string($key),
            ));
        }

        return [
            'selected_keys' => $selectedKeys,
            'available_groups' => $this->availableGroups(),
        ];
    }

    /**
     * Quick-link catalog grouped by sidebar section; source of truth for valid keys.
     *
     * @return list<array{key:string,label:string,items:list<array{key:string,label:string,href:string}>}>
     */
    public function availableGroups(): array
    {
        return [
            [
                'key' => 'sales',
                'label' => 'Sales',
                'items' => [
                    ['key' => 'pipeline', 'label' => 'Pipeline', 'href' => '/pipeline'],
                    ['key' => 'clients', 'label' => 'Clients', 'href' => '/clients'],
                    ['key' => 'contracts', 'label' => 'Contracts', 'href' => '/contracts'],
                    ['key' => 'find_space', 'label' => 'Find a space', 'href' => '/spaces/find'],
                ],
            ],
            [
                'key' => 'operations',
                'label' => 'Operations',
                'items' => [
                    ['key' => 'bookings', 'label' => 'Bookings', 'href' => '/bookings'],
                    ['key' => 'venues', 'label' => 'Venues', 'href' => '/venues'],
                    ['key' => 'ops_board', 'label' => 'Ops board', 'href' => '/ops/board'],
                    ['key' => 'schedule', 'label' => 'Schedule', 'href' => '/ops/schedule'],
                    ['key' => 'work_orders', 'label' => 'Work orders', 'href' => '/work-orders'],
                    ['key' => 'exhibitors', 'label' => 'Exhibitors', 'href' => '/exhibitors'],
                ],
            ],
            [
                'key' => 'finance',
                'label' => 'Finance',
                'items' => [
                    ['key' => 'accounting', 'label' => 'Accounting', 'href' => '/accounting'],
                    ['key' => 'invoices', 'label' => 'Invoices', 'href' => '/admin/invoices'],
                    ['key' => 'chart_of_accounts', 'label' => 'Chart of Accounts', 'href' => '/admin/chart-of-accounts'],
                    ['key' => 'funds', 'label' => 'Funds', 'href' => '/admin/funds'],
                    ['key' => 'fiscal_years', 'label' => 'Fiscal years', 'href' => '/admin/fiscal-years'],
                    ['key' => 'export_templates', 'label' => 'Export templates', 'href' => '/admin/export-templates'],
                ],
            ],
            [
                'key' => 'reporting',
                'label' => 'Reporting',
                'items' => [
                    ['key' => 'reports', 'label' => 'Reports', 'href' => '/reports'],
                    ['key' => 'report_builder', 'label' => 'Report builder', 'href' => '/admin/report-builder'],
                ],
            ],
            [
                'key' => 'admin',
                'label' => 'Admin',
                'items' => [
                    ['key' => 'users', 'label' => 'Users', 'href' => '/admin/users'],
                    ['key' => 'audit', 'label' => 'Audit log', 'href' => '/admin/audit'],
                    ['key' => 'system_settings', 'label' => 'System settings', 'href' => '/admin/system-settings'],
                    ['key' => 'handbook', 'label' => 'Handbook', 'href' => '/docs'],
                ],
            ],
        ];
    }
}
