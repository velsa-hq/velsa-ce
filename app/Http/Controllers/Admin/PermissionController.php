<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission audit views - read-only inspection of the RBAC graph (no mutation;
 * use /admin/roles or /admin/users for changes). Answers "who can do X, and at
 * which venues?" and "what can user Y do, by venue?".
 */
class PermissionController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(): Response
    {
        $perms = Permission::query()
            ->orderBy('name')
            ->get();

        // role grant counts per permission
        $grantsByPermission = DB::table('role_has_permissions')
            ->select('permission_id', DB::raw('count(*) as role_count'))
            ->groupBy('permission_id')
            ->pluck('role_count', 'permission_id');

        // distinct user counts per permission, via roles
        $userCountByPermission = DB::table('role_has_permissions as rhp')
            ->join('model_has_roles as mhr', 'mhr.role_id', '=', 'rhp.role_id')
            ->where('mhr.model_type', User::class)
            ->select('rhp.permission_id', DB::raw('count(distinct mhr.model_id) as user_count'))
            ->groupBy('rhp.permission_id')
            ->pluck('user_count', 'rhp.permission_id');

        $groups = [];
        foreach ($perms as $perm) {
            $module = $this->moduleLabel($perm->name);
            $groups[$module][] = [
                'name' => $perm->name,
                'action' => (string) (explode('.', $perm->name, 2)[1] ?? $perm->name),
                'role_count' => (int) ($grantsByPermission[$perm->id] ?? 0),
                'user_count' => (int) ($userCountByPermission[$perm->id] ?? 0),
                'is_custom' => ! $this->isSystem($perm->name),
            ];
        }

        return Inertia::render('admin/permissions/index', [
            'groups' => collect($groups)
                ->map(fn ($perms, $label) => [
                    'label' => $label,
                    'permissions' => $perms,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Register a new custom permission (global, team-agnostic). Lets admins add
     * their own permission strings without a code change. Built-ins are reserved.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                'regex:/^[a-z][a-z0-9_]*\.[a-z0-9_]+$/',
                Rule::unique('permissions', 'name'),
            ],
        ]);

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);

        try {
            Permission::findOrCreate($data['name']);
            $registrar->forgetCachedPermissions();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }

        $this->audit->record(eventType: 'permission.created', payload: ['name' => $data['name']]);

        return back()->with('toast', ['type' => 'success', 'message' => "Permission '{$data['name']}' added."]);
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($this->isSystem($permission->name)) {
            abort(403, 'Built-in permissions cannot be deleted.');
        }

        $name = $permission->name;

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);

        try {
            $permission->delete();
            $registrar->forgetCachedPermissions();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }

        $this->audit->record(eventType: 'permission.deleted', payload: ['name' => $name]);

        return back()->with('toast', ['type' => 'success', 'message' => "Permission '{$name}' removed."]);
    }

    protected function isSystem(string $name): bool
    {
        return in_array($name, RolesAndPermissionsSeeder::PERMISSIONS, true);
    }

    public function show(string $name): Response
    {
        $permission = Permission::query()->where('name', $name)->firstOrFail();

        $rolesWithPermission = DB::table('role_has_permissions as rhp')
            ->join('roles', 'roles.id', '=', 'rhp.role_id')
            ->where('rhp.permission_id', $permission->id)
            ->orderBy('roles.name')
            ->pluck('roles.name', 'roles.id');

        // walk model_has_roles for each granting role, joined to venues +
        // users, grouped by venue
        $assignments = DB::table('model_has_roles as mhr')
            ->join('users', function ($join) {
                $join->on('users.id', '=', 'mhr.model_id')
                    ->where('mhr.model_type', User::class);
            })
            ->join('venues', 'venues.id', '=', 'mhr.venue_id')
            ->join('roles', 'roles.id', '=', 'mhr.role_id')
            ->whereIn('mhr.role_id', $rolesWithPermission->keys())
            ->select(
                'venues.id as venue_id',
                'venues.name as venue_name',
                'venues.slug as venue_slug',
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email',
                'roles.name as via_role',
            )
            ->orderBy('venues.name')
            ->orderBy('users.name')
            ->get()
            ->groupBy('venue_id')
            ->map(fn ($rows) => [
                'venue_id' => (int) $rows->first()->venue_id,
                'venue_name' => $rows->first()->venue_name,
                'venue_slug' => $rows->first()->venue_slug,
                'users' => $rows->map(fn ($r) => [
                    'id' => (int) $r->user_id,
                    'name' => $r->user_name,
                    'email' => $r->user_email,
                    'via_role' => $r->via_role,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/permissions/show', [
            'permission' => [
                'name' => $permission->name,
                'module' => $this->moduleLabel($permission->name),
                'granted_by_roles' => $rolesWithPermission->values()->all(),
            ],
            'assignments' => $assignments,
        ]);
    }

    public function userMatrix(User $user): Response
    {
        // every (venue, role) the user holds
        $assignments = DB::table('model_has_roles as mhr')
            ->join('venues', 'venues.id', '=', 'mhr.venue_id')
            ->join('roles', 'roles.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', User::class)
            ->where('mhr.model_id', $user->id)
            ->select(
                'venues.id as venue_id',
                'venues.name as venue_name',
                'venues.slug as venue_slug',
                'roles.id as role_id',
                'roles.name as role_name',
            )
            ->get();

        $venues = $assignments
            ->unique('venue_id')
            ->sortBy('venue_name')
            ->map(fn ($a) => [
                'id' => (int) $a->venue_id,
                'name' => $a->venue_name,
                'slug' => $a->venue_slug,
            ])
            ->values()
            ->all();

        // role -> permission map
        $rolePerms = DB::table('role_has_permissions as rhp')
            ->join('permissions', 'permissions.id', '=', 'rhp.permission_id')
            ->whereIn('rhp.role_id', $assignments->pluck('role_id')->unique())
            ->select('rhp.role_id', 'permissions.name')
            ->get()
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        // for each venue, the union of permissions across every role the user
        // holds there
        $venuePerms = [];
        foreach ($assignments as $a) {
            $venuePerms[$a->venue_id] = array_values(array_unique(array_merge(
                $venuePerms[$a->venue_id] ?? [],
                $rolePerms->get($a->role_id, []),
            )));
        }

        $permRows = Permission::query()
            ->orderBy('name')
            ->get()
            ->map(function (Permission $p) use ($venuePerms, $venues) {
                $granted = [];
                foreach ($venues as $venue) {
                    $granted[(string) $venue['id']] = in_array($p->name, $venuePerms[$venue['id']] ?? [], true);
                }

                return [
                    'name' => $p->name,
                    'module' => $this->moduleLabel($p->name),
                    'granted' => $granted,
                ];
            })
            ->all();

        return Inertia::render('admin/users/permissions', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'venues' => $venues,
            'roles_by_venue' => $assignments
                ->groupBy('venue_id')
                ->map(fn ($rows) => $rows->pluck('role_name')->unique()->values()->all())
                ->all(),
            'permissions' => $permRows,
        ]);
    }

    protected function moduleLabel(string $permissionName): string
    {
        $prefix = explode('.', $permissionName, 2)[0];

        return match ($prefix) {
            'venues', 'spaces' => 'Venues + spaces',
            'bookings' => 'Bookings',
            'contracts', 'templates' => 'Contracts',
            'clients', 'leads', 'pipeline' => 'Sales',
            'exhibitors' => 'Exhibitors',
            'workorders' => 'Work orders',
            'accounting', 'payments' => 'Accounting',
            'reports' => 'Reporting',
            'users', 'permissions', 'audit', 'system' => 'Admin',
            default => 'Other',
        };
    }
}
