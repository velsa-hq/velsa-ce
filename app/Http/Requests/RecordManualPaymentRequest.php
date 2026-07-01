<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class RecordManualPaymentRequest extends FormRequest
{
    /**
     * Authorization stays at the call site. Shared across the staff path (inline
     * venue-permission check) and the admin path (route-group middleware) - two
     * different access models - so the controller/route keeps its own gate and
     * this returns true. Centralizing the rules here also gives the manual
     * payment-method whitelist a single home.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['check', 'wire', 'cash', 'ach'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
