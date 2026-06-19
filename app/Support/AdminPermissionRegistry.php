<?php

namespace App\Support;

/**
 * Maps an admin route name + HTTP method to the permission it requires.
 * Fail-closed: an unmapped route requires `system.settings`, so a new admin
 * route is gated to top admins by default rather than left open.
 */
final class AdminPermissionRegistry
{
    public static function permissionFor(string $routeName, string $method): string
    {
        $name = str_starts_with($routeName, 'admin.') ? substr($routeName, 6) : $routeName;
        $area = explode('.', $name)[0];
        $isWrite = ! in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);

        return match (true) {
            str_starts_with($name, 'users.') => $isWrite ? 'users.manage' : 'users.view',
            in_array($area, ['roles', 'permissions', 'sso-mappings'], true) => 'permissions.manage',
            $name === 'audit.export' => 'audit.export.raw',
            $area === 'audit', $area === 'audit-rules' => 'audit.view',
            in_array($area, ['layout-templates', 'document-templates'], true) => 'templates.manage',
            $area === 'spaces' => 'spaces.manage',
            in_array($area, [
                'space-kinds', 'event-kinds', 'inventory-kinds', 'departments',
                'outline-item-templates', 'work-order-templates', 'system-settings',
                'pipeline-stages', 'support-requests', 'branding-images',
            ], true) => 'system.settings',
            $area === 'imports' => 'data.import',
            $area === 'insurance-certificates' => $isWrite ? 'compliance.manage' : 'compliance.view',
            $area === 'exhibitor-permits' => $isWrite ? 'compliance.manage' : 'compliance.view',
            in_array($area, ['rate-cards', 'rate-packages'], true) => $isWrite ? 'pricing.manage' : 'pricing.view',
            $area === 'export-templates' => 'accounting.export_ledger',
            in_array($area, ['chart-of-accounts', 'funds'], true) => $isWrite ? 'accounting.post_journal' : 'accounting.view',
            $area === 'fiscal-years' => $isWrite ? 'accounting.post_journal' : 'accounting.view',
            $name === 'invoices.payments.refund', $name === 'invoices.refund' => 'payments.refund',
            $name === 'invoices.payments.record' => 'payments.process',
            $area === 'invoices' => $isWrite ? 'accounting.post_journal' : 'accounting.view',
            $area === 'bookings' => 'accounting.post_journal',
            $area === 'exhibitors' => 'accounting.view',
            $area === 'report-builder' => $isWrite ? 'reports.create' : 'reports.view',
            $area === 'sales-goals' => 'sales.manage_goals',
            default => 'system.settings',
        };
    }
}
