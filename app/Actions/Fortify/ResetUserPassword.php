<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\Auth\PasswordPolicy;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly PasswordPolicy $policy) {}

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        // reuse prohibition still applies on reset; minimum-age does not (a
        // locked-out user must be able to recover, no old plaintext to diff)
        $this->policy->validate($user, $input['password'], currentPlain: null, isReset: true);

        $user->forceFill([
            'password' => $input['password'],
        ])->save();

        $this->policy->record($user, $user->password);
    }
}
