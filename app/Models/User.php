<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_active_at' => 'datetime',
            'last_login_at' => 'datetime',
            'previous_login_at' => 'datetime',
            'force_logout_at' => 'datetime',
            'sso_provisioned_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'force_password_change' => 'boolean',
            'dashboard_preferences' => 'array',
            'whats_new_seen_at' => 'datetime',
        ];
    }

    /**
     * Lowercase email on write: Postgres `=` and unique indexes are
     * case-sensitive, so mixed-case would break login or duplicate rows.
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim($value));
    }

    /**
     * Role name held at the given venue, or null.
     * Spatie team-scoped tables: team_foreign_key = venue_id.
     */
    public function roleAt(Venue $venue): ?string
    {
        return $this->withVenueScope($venue, function () {
            return $this->roles()->first()?->name;
        });
    }

    public function canAt(Venue $venue, string $permission): bool
    {
        return $this->withVenueScope($venue, fn () => $this->hasPermissionTo($permission));
    }

    /**
     * Union of permissions across all venues the user has a role at; for
     * global surfaces not scoped to one venue. Memoized per request.
     *
     * @var list<string>|null
     */
    protected ?array $venuePermissionCache = null;

    /** @var list<int>|null */
    protected ?array $accessibleVenueIdCache = null;

    /**
     * @return list<string>
     */
    public function venuePermissionNames(): array
    {
        if ($this->venuePermissionCache !== null) {
            return $this->venuePermissionCache;
        }

        $names = [];
        foreach ($this->accessibleVenues() as $venue) {
            $this->withVenueScope($venue, function () use (&$names): void {
                $names = array_merge(
                    $names,
                    $this->getAllPermissions()->pluck('name')->all(),
                );
            });
        }

        return $this->venuePermissionCache = array_values(array_unique($names));
    }

    public function hasVenuePermission(string $permission): bool
    {
        return in_array($permission, $this->venuePermissionNames(), true);
    }

    public function assignRoleAt(Venue $venue, string $roleName): void
    {
        $this->withVenueScope($venue, fn () => $this->assignRole($roleName));
    }

    public function revokeAllRolesAt(Venue $venue): void
    {
        $this->withVenueScope($venue, function () {
            $this->roles()->each(fn ($role) => $this->removeRole($role));
        });
    }

    /**
     * @return Collection<int, Venue>
     */
    public function accessibleVenues(): Collection
    {
        $venueIds = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_id', $this->getKey())
            ->where('model_type', static::class)
            ->distinct()
            ->pluck('venue_id');

        return Venue::query()
            ->whereIn('id', $venueIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Memoized venue ids the user has a role at; drives VenueIsolationScope,
     * which runs on every venue-scoped query when isolation is enabled.
     *
     * @return list<int>
     */
    public function accessibleVenueIds(): array
    {
        if ($this->accessibleVenueIdCache !== null) {
            return $this->accessibleVenueIdCache;
        }

        return $this->accessibleVenueIdCache = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_id', $this->getKey())
            ->where('model_type', static::class)
            ->whereNotNull('venue_id')
            ->distinct()
            ->pluck('venue_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isDisabled(): bool
    {
        return $this->disabled_reason !== null;
    }

    /**
     * Run a callback with the permission registrar scoped to the venue;
     * restores the prior team id on exit.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withVenueScope(Venue $venue, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        // HasRoles caches loaded roles/permissions; drop them so the next
        // lookup re-reads model_has_roles under the new venue scope
        $this->unsetRelation('roles');
        $this->unsetRelation('permissions');

        try {
            $registrar->setPermissionsTeamId($venue->getKey());

            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $this->unsetRelation('roles');
            $this->unsetRelation('permissions');
        }
    }
}
