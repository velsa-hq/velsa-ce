<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the RBAC scaffolding. Roles and permissions are global
 * (team_id = null); per-venue assignment lives in model_has_roles
 * (venue_id), driven by middleware setting the registrar team id per
 * request. Idempotent, keyed on name+guard.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        // venue + space management
        'venues.view',
        'venues.manage',
        // bypass for optional venue data isolation: holders see every
        // venue's data even when isolation is on
        'venues.view_all',
        'spaces.view',
        'spaces.manage',

        // bookings & scheduling
        'bookings.view',
        'bookings.create',
        'bookings.edit',
        'bookings.approve',
        'bookings.delete',
        'bookings.hold_release',

        // contracts
        'contracts.view',
        'contracts.create',
        'contracts.send',
        'contracts.sign_on_behalf',
        'templates.manage',

        // sales pipeline
        'clients.view',
        'clients.manage',
        'leads.view',
        'leads.manage',
        'pipeline.view',
        'sales.manage_goals',

        // exhibitor orders
        'exhibitors.manage',

        // insurance / COI compliance tracking
        'compliance.view',
        'compliance.manage',

        // pricing: rate cards + packages/bundles
        'pricing.view',
        'pricing.manage',

        // work orders
        'workorders.view',
        'workorders.manage',
        'workorders.complete',

        // accounting + payments
        'accounting.view',
        'accounting.post_journal',
        'accounting.export_ledger',
        'payments.view',
        'payments.process',
        'payments.refund',

        // reporting
        'reports.view',
        'reports.create',
        'reports.schedule',

        // data import
        'data.import',

        // cross-cutting admin
        'users.view',
        'users.manage',
        'permissions.manage',
        'audit.view',
        'audit.export.raw',
        'system.settings',
    ];

    /**
     * Role -> permission grants. `*` grants every permission.
     *
     * @var array<string, array<int, string>>
     */
    public const ROLES = [
        'super_admin' => ['*'],

        'org_admin' => [
            'venues.view', 'venues.manage', 'venues.view_all', 'spaces.view', 'spaces.manage',
            'bookings.view', 'bookings.create', 'bookings.edit', 'bookings.approve', 'bookings.delete', 'bookings.hold_release',
            'contracts.view', 'contracts.create', 'contracts.send', 'templates.manage',
            'clients.view', 'clients.manage', 'leads.view', 'leads.manage', 'pipeline.view',
            'sales.manage_goals', 'exhibitors.manage',
            'compliance.view', 'compliance.manage',
            'pricing.view', 'pricing.manage',
            'workorders.view', 'workorders.manage',
            'accounting.view', 'accounting.post_journal', 'accounting.export_ledger',
            'payments.view', 'payments.process', 'payments.refund',
            'reports.view', 'reports.create', 'reports.schedule',
            'data.import',
            'users.view', 'users.manage', 'permissions.manage',
            'audit.view', 'audit.export.raw',
            'system.settings',
        ],

        'venue_admin' => [
            'venues.view', 'venues.manage', 'spaces.view', 'spaces.manage',
            'bookings.view', 'bookings.create', 'bookings.edit', 'bookings.approve', 'bookings.hold_release',
            'contracts.view', 'contracts.create', 'contracts.send', 'templates.manage',
            'clients.view', 'clients.manage', 'leads.view', 'leads.manage', 'pipeline.view',
            'sales.manage_goals', 'exhibitors.manage',
            'compliance.view', 'compliance.manage',
            'pricing.view', 'pricing.manage',
            'workorders.view', 'workorders.manage',
            'reports.view', 'reports.create',
            'data.import',
            'users.view',
            'audit.view',
        ],

        'sales_manager' => [
            'venues.view', 'spaces.view',
            'bookings.view', 'bookings.create', 'bookings.edit', 'bookings.approve', 'bookings.hold_release',
            'contracts.view', 'contracts.create', 'contracts.send',
            'clients.view', 'clients.manage', 'leads.view', 'leads.manage', 'pipeline.view',
            'sales.manage_goals', 'reports.view',
        ],

        'sales_rep' => [
            'venues.view', 'spaces.view',
            'bookings.view', 'bookings.create', 'bookings.edit',
            'contracts.view', 'contracts.create',
            'clients.view', 'clients.manage', 'leads.view', 'leads.manage', 'pipeline.view',
        ],

        'event_coordinator' => [
            'venues.view', 'spaces.view',
            'bookings.view', 'bookings.edit',
            'workorders.view', 'workorders.manage', 'workorders.complete',
        ],

        'ops_lead' => [
            'venues.view', 'spaces.view',
            'bookings.view',
            'workorders.view', 'workorders.manage', 'workorders.complete',
        ],

        'finance' => [
            'venues.view',
            'bookings.view',
            'contracts.view',
            'accounting.view', 'accounting.post_journal', 'accounting.export_ledger',
            'payments.view', 'payments.process', 'payments.refund',
            'reports.view',
            'audit.view',
        ],

        'read_only' => [
            'venues.view', 'spaces.view',
            'bookings.view',
            'contracts.view',
            'clients.view', 'leads.view', 'pipeline.view',
            'workorders.view',
            'accounting.view', 'payments.view',
            'reports.view',
        ],

        // public demo role: operationally rich but deliberately excludes
        // every privileged/abuse surface - no system.settings (SSRF +
        // secret surface), no users.manage/permissions.manage, no audit.*,
        // no data.import, no accounting/payment writes. Pairs with safe
        // mode (mail/e-sign/payments inert), see App\Support\SafeMode.
        'demo' => [
            'venues.view', 'venues.manage', 'spaces.view', 'spaces.manage',
            'bookings.view', 'bookings.create', 'bookings.edit', 'bookings.approve', 'bookings.hold_release',
            'contracts.view', 'contracts.create', 'contracts.send', 'templates.manage',
            'clients.view', 'clients.manage', 'leads.view', 'leads.manage', 'pipeline.view',
            'sales.manage_goals', 'exhibitors.manage',
            'compliance.view', 'compliance.manage',
            'pricing.view', 'pricing.manage',
            'workorders.view', 'workorders.manage',
            'accounting.view', 'payments.view',
            'reports.view',
        ],

        // portal-only role for external exhibitors (magic link); access to
        // their own ExhibitorOrder is gated by policy, not this list
        'exhibitor' => [],

        // external contractor, read-only on a single venue
        'contractor' => [
            'venues.view', 'spaces.view',
            'bookings.view',
            'workorders.view',
        ],
    ];

    public function run(): void
    {
        // global (team_id = null): one definition per role/permission name;
        // venue scoping lives in the model_has_roles pivot
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);

        try {
            foreach (self::PERMISSIONS as $permission) {
                Permission::findOrCreate($permission);
            }

            foreach (self::ROLES as $name => $grants) {
                $role = Role::findOrCreate($name);

                $resolved = $grants === ['*']
                    ? self::PERMISSIONS
                    : $grants;

                $role->syncPermissions($resolved);
            }

            $registrar->forgetCachedPermissions();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
