<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChargeCardRequest extends FormRequest
{
    /**
     * Authorization stays at the call site. This request is shared across the
     * staff path (gated by an inline venue-permission check) and the exhibitor
     * portal path (gated by the portal session guard) - two different access
     * models - so the controller/route keeps its own gate and this returns true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'card_token' => ['required', 'string', 'max:120'],
            'amount_cents' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
