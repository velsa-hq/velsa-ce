<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Custom role management. Built-in roles seeded by RolesAndPermissionsSeeder
 * are locked from edit + delete. Names are validated as snake_case to stay
 * uniform with the built-in set.
 */
class RoleController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(): Response
    {
        $userCounts = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->select('role_id', DB::raw('count(distinct model_id) as user_count'))
            ->groupBy('role_id')
            ->pluck('user_count', 'role_id');

        $rows = Role::query()
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'permission_count' => (int) $r->permissions_count,
                'user_count' => (int) ($userCounts[$r->id] ?? 0),
                'is_built_in' => $this->isBuiltIn($r->name),
            ])
            ->all();

        return Inertia::render('admin/roles/index', [
            'roles' => $rows,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/roles/form', [
            'mode' => 'create',
            'role' => [
                'id' => null,
                'name' => '',
                'permissions' => [],
                'is_built_in' => false,
                'user_count' => 0,
            ],
            'permission_groups' => $this->permissionGroups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRolePayload($request, ignoreId: null);

        /** @var Role $role */
        $role = Role::create(['name' => $data['name']]);
        $role->syncPermissions($data['permissions']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->audit->record(
            eventType: 'role.created',
            subject: $role,
            payload: ['permissions' => $data['permissions']],
        );

        return to_route('admin.roles.show', $role);
    }

    // create form pre-filled from an existing role; the clone is always a fresh custom role
    public function clone(Role $role): Response
    {
        return Inertia::render('admin/roles/form', [
            'mode' => 'create',
            'role' => [
                'id' => null,
                'name' => Str::of($role->name)->append('_copy')->limit(64, '')->value(),
                'permissions' => $role->permissions->pluck('name')->all(),
                'is_built_in' => false,
                'user_count' => 0,
            ],
            'permission_groups' => $this->permissionGroups(),
            'cloned_from' => $role->name,
        ]);
    }

    public function show(Role $role): Response
    {
        $userCount = (int) DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('role_id', $role->id)
            ->distinct('model_id')
            ->count('model_id');

        return Inertia::render('admin/roles/form', [
            'mode' => 'edit',
            'role' => [
                'id' => (int) $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->all(),
                'is_built_in' => $this->isBuiltIn($role->name),
                'user_count' => $userCount,
            ],
            'permission_groups' => $this->permissionGroups(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($this->isBuiltIn($role->name)) {
            abort(403, 'Built-in roles cannot be edited.');
        }

        $data = $this->validateRolePayload($request, ignoreId: $role->id);

        $before = $role->permissions->pluck('name')->all();
        $renamed = $role->name !== $data['name'];

        $role->forceFill(['name' => $data['name']])->save();
        $role->syncPermissions($data['permissions']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->audit->record(
            eventType: 'role.updated',
            subject: $role,
            payload: [
                'renamed' => $renamed,
                'permissions_before' => $before,
                'permissions_after' => $data['permissions'],
            ],
        );

        return back();
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($this->isBuiltIn($role->name)) {
            abort(403, 'Built-in roles cannot be deleted.');
        }

        $userCount = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('role_id', $role->id)
            ->count();

        if ($userCount > 0) {
            return back()->withErrors([
                'role' => "Can't delete a role that's still assigned to {$userCount} user".($userCount === 1 ? '' : 's').'. Remove the assignments first.',
            ]);
        }

        $name = $role->name;
        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->audit->record(
            eventType: 'role.deleted',
            payload: ['name' => $name],
        );

        return to_route('admin.roles.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateRolePayload(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->ignore($ignoreId),
                fn ($attr, $value, $fail) => $this->isBuiltIn($value)
                    ? $fail('That name collides with a built-in role.')
                    : null,
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::query()->pluck('name')->all())],
        ]);
    }

    protected function isBuiltIn(string $roleName): bool
    {
        return array_key_exists($roleName, RolesAndPermissionsSeeder::ROLES);
    }

    /**
     * Group permissions by their module.action prefix for module-scoped UI blocks.
     *
     * @return list<array{key:string,label:string,permissions:list<array{name:string,action:string}>}>
     */
    protected function permissionGroups(): array
    {
        $groups = [
            'venues' => 'Venues + spaces',
            'spaces' => 'Venues + spaces',
            'bookings' => 'Bookings',
            'contracts' => 'Contracts',
            'templates' => 'Contracts',
            'clients' => 'Sales',
            'leads' => 'Sales',
            'pipeline' => 'Sales',
            'exhibitors' => 'Exhibitors',
            'workorders' => 'Work orders',
            'accounting' => 'Accounting',
            'payments' => 'Accounting',
            'reports' => 'Reporting',
            'users' => 'Admin',
            'permissions' => 'Admin',
            'audit' => 'Admin',
            'system' => 'Admin',
        ];

        $bucketed = [];
        foreach (Permission::query()->orderBy('name')->pluck('name') as $name) {
            $prefix = explode('.', $name, 2)[0];
            $label = $groups[$prefix] ?? 'Other';
            $bucketed[$label][] = [
                'name' => $name,
                'action' => (string) (explode('.', $name, 2)[1] ?? $name),
            ];
        }

        $order = [
            'Venues + spaces', 'Bookings', 'Contracts', 'Sales', 'Exhibitors',
            'Work orders', 'Accounting', 'Reporting', 'Admin', 'Other',
        ];

        $result = [];
        foreach ($order as $label) {
            if (! empty($bucketed[$label])) {
                $result[] = [
                    'key' => strtolower(str_replace([' ', '+', '/'], '_', $label)),
                    'label' => $label,
                    'permissions' => $bucketed[$label],
                ];
            }
        }

        return $result;
    }
}
