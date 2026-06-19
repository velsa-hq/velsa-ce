<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Venue;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    /**
     * Privilege-escalation guard for every role-granting path: only a super
     * admin may grant super_admin. Without it, a lower admin holding
     * users.manage could mint an account above its own level.
     */
    private function assertCanGrantRole(?User $actor, string $role): void
    {
        // super_admin is a hard ceiling: it bypasses permission checks via
        // Gate, so it can't be expressed as a permission subset below
        if ($role === 'super_admin' && ! $this->actorIsSuperAdmin($actor)) {
            throw ValidationException::withMessages([
                'role' => 'Only a super administrator may grant the super_admin role.',
            ]);
        }

        // a super admin may grant anything; everyone else may only grant a
        // role whose permissions are a subset of their own
        if ($actor === null || $this->actorIsSuperAdmin($actor)) {
            return;
        }

        $rolePermissions = Role::query()
            ->where('name', $role)
            ->with('permissions:id,name')
            ->first()?->permissions->pluck('name')->all() ?? [];

        $exceeds = array_diff($rolePermissions, $actor->venuePermissionNames());

        if ($exceeds !== []) {
            throw ValidationException::withMessages([
                'role' => 'You may only grant roles whose permissions you hold yourself.',
            ]);
        }
    }

    /**
     * Whether the actor holds super_admin at any venue (it's a global role -
     * one assignment confers it everywhere).
     */
    private function actorIsSuperAdmin(?User $actor): bool
    {
        return $actor !== null && DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $actor->id)
            ->where('roles.name', 'super_admin')
            ->exists();
    }

    /**
     * Roles the actor may hand out. super_admin is hidden from the picker for
     * non-super-admins (the server-side ceiling rejects it anyway).
     *
     * @return list<string>
     */
    private function grantableRoles(?User $actor): array
    {
        $roles = Role::query()->orderBy('name')->pluck('name');

        if (! $this->actorIsSuperAdmin($actor)) {
            $roles = $roles->reject(fn (string $r) => $r === 'super_admin');
        }

        return $roles->values()->all();
    }

    /**
     * Resolve the assignment target into venues: the "all" sentinel fans the
     * grant across every active venue (for org-wide roles).
     *
     * @return Collection<int, Venue>
     */
    private function assignmentVenues(string|int $venueParam): Collection
    {
        if ($venueParam === 'all') {
            return Venue::query()->active()->orderBy('name')->get();
        }

        return Venue::query()->whereKey($venueParam)->get();
    }

    public function index(Request $request): Response
    {
        $q = trim($request->string('q')->toString());

        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('email')
            ->get([
                'id', 'name', 'email',
                'license_tier', 'disabled_reason',
                'last_active_at', 'email_verified_at',
            ]);

        $assignments = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('venues', 'venues.id', '=', 'model_has_roles.venue_id')
            ->where('model_has_roles.model_type', User::class)
            ->select(
                'model_has_roles.model_id as user_id',
                'model_has_roles.expires_at',
                'venues.slug as venue_slug',
                'venues.name as venue_name',
                'roles.name as role',
            )
            ->orderBy('venues.name')
            ->get()
            ->groupBy('user_id');

        $rows = $users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'license_tier' => $user->license_tier,
            'disabled_reason' => $user->disabled_reason,
            'last_active_at' => $user->last_active_at?->toIso8601String(),
            'email_verified' => $user->email_verified_at !== null,
            'assignments' => $assignments
                ->get($user->id, collect())
                ->map(fn ($row) => [
                    'venue_slug' => $row->venue_slug,
                    'venue_name' => $row->venue_name,
                    'role' => $row->role,
                    'expires_at' => $row->expires_at,
                ])
                ->values(),
        ]);

        return Inertia::render('admin/users/index', [
            'users' => $rows,
            'filters' => ['q' => $q],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $assignments = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('venues', 'venues.id', '=', 'model_has_roles.venue_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->select(
                'venues.id as venue_id',
                'venues.name as venue_name',
                'venues.slug as venue_slug',
                'roles.name as role',
                'model_has_roles.expires_at',
            )
            ->orderBy('venues.name')
            ->orderBy('roles.name')
            ->get()
            ->map(fn ($row) => [
                'venue_id' => (int) $row->venue_id,
                'venue_slug' => $row->venue_slug,
                'venue_name' => $row->venue_name,
                'role' => $row->role,
                'expires_at' => $row->expires_at,
            ])
            ->all();

        return Inertia::render('admin/users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'license_tier' => $user->license_tier,
                'disabled_reason' => $user->disabled_reason,
                'email_verified' => $user->email_verified_at !== null,
                'last_active_at' => $user->last_active_at?->toIso8601String(),
                'sso_provisioned_at' => $user->sso_provisioned_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'assignments' => $assignments,
            ],
            'roles' => $this->grantableRoles($request->user()),
            'venues' => Venue::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->all(),
            // lets the UI collapse a role held at every active venue into one
            // "All venues" row
            'active_venue_count' => Venue::query()->active()->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/users/create', [
            'roles' => $this->grantableRoles($request->user()),
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug'])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // normalize email before the uniqueness check so a case-variant can't
        // slip past it (the model lowercases on save)
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Password::defaults()],
            'venue_id' => ['nullable', $this->venueAssignmentRule()],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name'), 'required_with:venue_id'],
        ]);

        if (! empty($data['role'])) {
            $this->assertCanGrantRole($request->user(), $data['role']);
        }

        // create + initial role assignment atomically so a failed grant leaves
        // no orphaned account
        $user = DB::transaction(function () use ($data) {
            // `password` is cast to `hashed`, so plaintext is hashed on save
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            // admin-created accounts are pre-verified; the admin-set password
            // must change at first sign-in (STIG APSC-DV-001790)
            $user->forceFill([
                'email_verified_at' => now(),
                'force_password_change' => true,
            ])->save();

            if (! empty($data['venue_id']) && ! empty($data['role'])) {
                foreach ($this->assignmentVenues($data['venue_id']) as $venue) {
                    $user->assignRoleAt($venue, $data['role']);
                }
            }

            return $user;
        });

        // Auditable fires user.created; this adds the admin-provisioning
        // context (who/what role)
        $this->audit->record(
            eventType: 'user.created_by_admin',
            subject: $user,
            payload: ['email' => $user->email, 'initial_role' => $data['role'] ?? null],
        );

        return to_route('admin.users.show', $user)
            ->with('toast', ['type' => 'success', 'message' => 'User created.']);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $before = ['name' => $user->name, 'email' => $user->email];
        $user->forceFill($data)->save();

        // Auditable fires user.updated with the after-snapshot; this adds the
        // before/after delta so the log shows what changed
        $this->audit->record(
            eventType: 'user.profile_edited',
            subject: $user,
            payload: ['before' => $before, 'after' => $data],
        );

        return back();
    }

    public function disable(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        // disable + revoke live sessions (force_logout_at is honored by
        // IdleTimeoutMiddleware next request); terminate on disable
        // (STIG APSC-DV-001800 / NIST IA-5(13))
        $user->forceFill(['disabled_reason' => $data['reason'], 'force_logout_at' => now()])->save();

        $this->audit->record(
            eventType: 'user.disabled',
            subject: $user,
            payload: ['reason' => $data['reason']],
        );

        return back();
    }

    public function enable(User $user): RedirectResponse
    {
        $previousReason = $user->disabled_reason;
        $user->forceFill(['disabled_reason' => null])->save();

        $this->audit->record(
            eventType: 'user.enabled',
            subject: $user,
            payload: ['previous_reason' => $previousReason],
        );

        return back();
    }

    /**
     * Require a new password at next sign-in - used after issuing a temporary
     * password (STIG APSC-DV-001790). Revokes live sessions so it takes effect
     * immediately; cleared when the user changes their password.
     */
    public function requirePasswordChange(User $user): RedirectResponse
    {
        $user->forceFill(['force_password_change' => true, 'force_logout_at' => now()])->save();

        $this->audit->record(
            eventType: 'user.password_change_required',
            subject: $user,
        );

        return back();
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'venue_id' => ['required', $this->venueAssignmentRule()],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $this->assertCanGrantRole($request->user(), $data['role']);

        $venues = $this->assignmentVenues($data['venue_id']);
        $roleIds = Role::query()->where('name', $data['role'])->pluck('id');

        // fan out across one venue or all of them, atomically
        DB::transaction(function () use ($user, $venues, $roleIds, $data) {
            foreach ($venues as $venue) {
                // Spatie's assignRole is additive; the model_has_roles PK stops
                // a duplicate (user, venue, role)
                $user->assignRoleAt($venue, $data['role']);

                // stamp the optional expiry on the pivot (Spatie doesn't know
                // expires_at); null clears a prior expiry
                DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('model_id', $user->id)
                    ->where('venue_id', $venue->id)
                    ->whereIn('role_id', $roleIds)
                    ->update(['expires_at' => $data['expires_at'] ?? null]);
            }
        });

        $this->audit->record(
            eventType: 'user.role_assigned',
            subject: $user,
            venue: $venues->count() === 1 ? $venues->first() : null,
            payload: [
                'role' => $data['role'],
                'venue_scope' => $data['venue_id'] === 'all' ? 'all venues' : $venues->first()?->slug,
                'expires_at' => $data['expires_at'] ?? null,
            ],
        );

        return back();
    }

    public function unassignRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'venue_id' => ['required', $this->venueAssignmentRule()],
            'role' => ['required', 'string'],
        ]);

        $venues = $this->assignmentVenues($data['venue_id']);
        $roleIds = Role::query()->where('name', $data['role'])->pluck('id');

        DB::transaction(function () use ($user, $venues, $roleIds) {
            foreach ($venues as $venue) {
                DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('model_id', $user->id)
                    ->where('venue_id', $venue->id)
                    ->whereIn('role_id', $roleIds)
                    ->delete();
            }
        });

        // clear Spatie's role cache so later permission checks see the change
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->audit->record(
            eventType: 'user.role_unassigned',
            subject: $user,
            venue: $venues->count() === 1 ? $venues->first() : null,
            payload: [
                'role' => $data['role'],
                'venue_scope' => $data['venue_id'] === 'all' ? 'all venues' : $venues->first()?->slug,
            ],
        );

        return back();
    }

    /**
     * Validation rule for a venue assignment target: the "all" sentinel or an
     * existing venue id.
     */
    private function venueAssignmentRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === 'all') {
                return;
            }

            if (! Venue::query()->whereKey($value)->exists()) {
                $fail('Select a venue or "All venues".');
            }
        };
    }
}
