<?php

namespace App\Services\Auth;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Configurable password lifetime/composition/reuse policy
 * (STIG IA-5(1) / CM-6, APSC-DV-001730/001760/001770/001780/001790).
 *
 * All checks default OFF (config values of 0): NIST 800-63B deprecates forced
 * rotation/composition, so the capability ships for STIG-bound deployments
 * without changing the default posture. Change-time checks run on every write
 * path; max-lifetime/forced-change is enforced by EnsurePasswordCurrent.
 */
class PasswordPolicy
{
    private function minAgeHours(): int
    {
        return (int) config('auth.password_policy.min_age_hours', 0);
    }

    private function maxAgeDays(): int
    {
        return (int) config('auth.password_policy.max_age_days', 0);
    }

    private function historyCount(): int
    {
        return (int) config('auth.password_policy.history_count', 0);
    }

    private function minChangedChars(): int
    {
        return (int) config('auth.password_policy.min_changed_chars', 0);
    }

    /**
     * Policy violations for a proposed password change.
     *
     * @return list<string> human-readable messages; empty means it passes
     */
    public function violations(User $user, string $newPassword, ?string $currentPlain, bool $isReset): array
    {
        $errors = [];

        // Minimum lifetime (APSC-DV-001760): voluntary changes only - never
        // block a forgotten-password reset, or a locked-out user can't recover.
        if (! $isReset && $this->minAgeHours() > 0 && $user->password_changed_at !== null) {
            $earliest = $user->password_changed_at->copy()->addHours($this->minAgeHours());
            if ($earliest->isFuture()) {
                $errors[] = __('Your password was changed too recently; it can be changed again :when.', [
                    'when' => $earliest->diffForHumans(),
                ]);
            }
        }

        // Composition (APSC-DV-001730): at least N characters must differ from
        // the old password. Needs the old plaintext, so in-app changes only.
        if (! $isReset && $this->minChangedChars() > 0 && $currentPlain !== null
            && $this->charsChanged($currentPlain, $newPassword) < $this->minChangedChars()) {
            $errors[] = __('The new password must differ from the current one by at least :n characters.', [
                'n' => $this->minChangedChars(),
            ]);
        }

        // Reuse (APSC-DV-001780): not the current password nor the last N.
        // Applies on reset too, so reset can't bypass the prohibition.
        if ($this->historyCount() > 0 && $this->isReused($user, $newPassword)) {
            $errors[] = __('This matches a password you have used recently; choose a different one.');
        }

        return $errors;
    }

    /**
     * Throw a ValidationException (keyed to $field) if the change is disallowed.
     */
    public function validate(User $user, string $newPassword, ?string $currentPlain, bool $isReset, string $field = 'password'): void
    {
        $errors = $this->violations($user, $newPassword, $currentPlain, $isReset);

        if ($errors !== []) {
            throw ValidationException::withMessages([$field => $errors]);
        }
    }

    /**
     * Record a just-set password: stamp the change time, clear any forced-change
     * flag, and (when reuse is enforced) push the hash into history, trimmed to
     * the configured depth. Pass the *hashed* value (User->password after save).
     */
    public function record(User $user, string $hashedPassword): void
    {
        $user->forceFill([
            'password_changed_at' => now(),
            'force_password_change' => false,
        ])->saveQuietly();

        if ($this->historyCount() <= 0) {
            return;
        }

        PasswordHistory::create(['user_id' => $user->getKey(), 'password' => $hashedPassword]);

        $keepIds = PasswordHistory::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->take($this->historyCount())
            ->pluck('id');

        PasswordHistory::query()
            ->where('user_id', $user->getKey())
            ->whereNotIn('id', $keepIds->all())
            ->delete();
    }

    /** Whether the user must change their password before proceeding. */
    public function mustChange(User $user): bool
    {
        return $user->force_password_change || $this->isExpired($user);
    }

    /** Maximum-lifetime expiry (APSC-DV-001770). */
    public function isExpired(User $user): bool
    {
        if ($this->maxAgeDays() <= 0 || $user->password_changed_at === null) {
            return false;
        }

        return $user->password_changed_at->copy()->addDays($this->maxAgeDays())->isPast();
    }

    private function isReused(User $user, string $newPassword): bool
    {
        // The current password counts as a generation even if it predates the
        // history table (e.g. users created before this feature).
        if ($user->password !== '' && Hash::check($newPassword, $user->password)) {
            return true;
        }

        $recent = PasswordHistory::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->take($this->historyCount())
            ->pluck('password');

        foreach ($recent as $hash) {
            if (Hash::check($newPassword, (string) $hash)) {
                return true;
            }
        }

        return false;
    }

    /** Edit distance old->new, a proxy for "number of characters changed". */
    private function charsChanged(string $old, string $new): int
    {
        // levenshtein() caps at 255-byte inputs; passwords are far shorter, but
        // guard anyway so an over-long input degrades to a length delta.
        if (strlen($old) > 255 || strlen($new) > 255) {
            return abs(strlen($new) - strlen($old));
        }

        return levenshtein($old, $new);
    }
}
