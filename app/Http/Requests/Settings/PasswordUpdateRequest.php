<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\Auth\PasswordPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * Apply the configurable password policy (min age / composition / reuse)
     * after the basic strength + current-password rules pass.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('current_password') || $validator->errors()->has('password')) {
                return;
            }

            /** @var User $user */
            $user = $this->user();

            foreach (app(PasswordPolicy::class)->violations(
                $user,
                (string) $this->input('password'),
                (string) $this->input('current_password'),
                isReset: false,
            ) as $message) {
                $validator->errors()->add('password', $message);
            }
        });
    }
}
