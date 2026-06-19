<?php

use App\Support\AdminPermissionRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// locks the admin route -> permission mapping and the fail-closed default;
// AdminRouteGateTest covers the behavioral gating, this covers the map itself
it('maps admin routes to least-privilege permissions', function (string $name, string $method, string $expected) {
    expect(AdminPermissionRegistry::permissionFor($name, $method))->toBe($expected);
})->with([
    'users read' => ['admin.users.index', 'GET', 'users.view'],
    'users write' => ['admin.users.store', 'POST', 'users.manage'],
    'roles' => ['admin.roles.index', 'GET', 'permissions.manage'],
    'permissions write' => ['admin.permissions.update', 'PUT', 'permissions.manage'],
    'sso mappings' => ['admin.sso-mappings.store', 'POST', 'permissions.manage'],
    'audit view' => ['admin.audit.index', 'GET', 'audit.view'],
    'audit raw export' => ['admin.audit.export', 'GET', 'audit.export.raw'],
    'imports' => ['admin.imports.store', 'POST', 'data.import'],
    'branding images read' => ['admin.branding-images.index', 'GET', 'system.settings'],
    'branding images write' => ['admin.branding-images.store', 'POST', 'system.settings'],
    'chart of accts read' => ['admin.chart-of-accounts.index', 'GET', 'accounting.view'],
    'chart of accts write' => ['admin.chart-of-accounts.store', 'POST', 'accounting.post_journal'],
    'export templates' => ['admin.export-templates.index', 'GET', 'accounting.export_ledger'],
    'refund' => ['admin.invoices.payments.refund', 'POST', 'payments.refund'],
    'exhibitor permits read' => ['admin.exhibitor-permits.index', 'GET', 'compliance.view'],
    'exhibitor permits write' => ['admin.exhibitor-permits.update', 'PUT', 'compliance.manage'],
    'report builder read' => ['admin.report-builder.index', 'GET', 'reports.view'],
    'report builder write' => ['admin.report-builder.store', 'POST', 'reports.create'],
    'unmapped fail-closed' => ['admin.some-future-route.index', 'GET', 'system.settings'],
])->group('stig', 'nist-AC-6');

it('never gates an admin route on a permission that does not exist', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $catalog = Permission::query()->pluck('name')->all();

    $offenders = [];
    foreach (Route::getRoutes() as $route) {
        $name = (string) $route->getName();
        if (! str_starts_with($name, 'admin.')) {
            continue;
        }
        foreach ($route->methods() as $method) {
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }
            $permission = AdminPermissionRegistry::permissionFor($name, $method);
            if (! in_array($permission, $catalog, true)) {
                $offenders[] = "{$name} [{$method}] -> {$permission}";
            }
        }
    }

    expect($offenders)->toBe([]);
})->group('stig', 'nist-AC-6');
