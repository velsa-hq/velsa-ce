<?php

namespace App\Services\Sso;

use App\Models\User;
use App\Models\Venue;

/**
 * Maps an SSO identity to a local User row. Two-step lookup: provider id is
 * the durable key, but the first sign-in won't have it stored, so fall back to
 * email and stamp the id on so later sign-ins skip the email path.
 */
class SsoUserResolver
{
    public function resolve(SsoIdentity $identity): User
    {
        $existing = User::query()
            ->where('sso_provider', $identity->provider)
            ->where('sso_id', $identity->providerUserId)
            ->first();

        if ($existing !== null) {
            $this->refreshEmail($existing, $identity);
            $this->ensureVerified($existing);

            return $existing;
        }

        $byEmail = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($identity->email)])
            ->first();

        if ($byEmail !== null) {
            // adopt the local account: stamp the id and mark verified
            // (trusted IdP asserts ownership, so app-level verification is redundant)
            $byEmail->forceFill([
                'sso_id' => $identity->providerUserId,
                'sso_provider' => $identity->provider,
                'sso_provisioned_at' => $byEmail->sso_provisioned_at ?? now(),
                'email_verified_at' => $byEmail->email_verified_at ?? now(),
            ])->save();

            return $byEmail;
        }

        return $this->provision($identity);
    }

    protected function refreshEmail(User $user, SsoIdentity $identity): void
    {
        if (mb_strtolower((string) $user->email) === mb_strtolower($identity->email)) {
            return;
        }

        // email changed upstream (e.g. UPN updated in Entra); sync and trust it as verified
        $user->forceFill([
            'email' => $identity->email,
            'email_verified_at' => now(),
        ])->save();
    }

    /**
     * Mark an SSO user email-verified - IdP already proved ownership, so they
     * are exempt from MustVerifyEmail.
     */
    protected function ensureVerified(User $user): void
    {
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }

    protected function provision(SsoIdentity $identity): User
    {
        $mode = (string) config('sso.provisioning', 'jit');
        if ($mode !== 'jit') {
            throw new SsoProvisioningDisallowedException(
                "SSO provisioning is set to '{$mode}' - no User row exists for {$identity->email}.",
            );
        }

        /** @var list<string> $bootstrapAdmins */
        $bootstrapAdmins = (array) config('sso.bootstrap_admin_emails', []);
        $isBootstrapAdmin = collect($bootstrapAdmins)
            ->contains(fn ($email) => mb_strtolower((string) $email) === mb_strtolower($identity->email));

        $user = new User;
        $user->forceFill([
            'name' => $identity->name ?: $identity->email,
            'email' => $identity->email,
            'sso_id' => $identity->providerUserId,
            'sso_provider' => $identity->provider,
            'sso_provisioned_at' => now(),
            'email_verified_at' => now(),
            // unusable password; SSO users never present one but the column is non-nullable
            'password' => bcrypt(bin2hex(random_bytes(32))),
        ])->save();

        $role = $isBootstrapAdmin
            ? (string) config('sso.bootstrap_admin_role', 'super_admin')
            : (string) config('sso.default_role', 'read_only');

        // roles are venue-scoped (Spatie teams = venues); assign at every active
        // venue so the JIT user can work on first sign-in; no venues -> roleless
        try {
            $venues = Venue::query()->active()->get();
            foreach ($venues as $venue) {
                $user->assignRoleAt($venue, $role);
            }
        } catch (\Throwable $e) {
            // role missing (seeder not run) or venues not migrated; leave roleless
            report($e);
        }

        return $user;
    }
}
