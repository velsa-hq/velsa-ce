<?php

namespace App\Http\Requests;

use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // authorize before validation: 403 not 422, so the rules don't leak (AC-3)
        return $this->user()?->can('create', Client::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = array_map(fn (ClientType $t) => $t->value, ClientType::cases());

        return [
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in($types)],
            'industry' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:200'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*.key' => ['nullable', 'string', 'max:60'],
            'custom_fields.*.value' => ['nullable', 'string', 'max:500'],
            // optional primary contact at creation time
            'contact' => ['nullable', 'array'],
            'contact.name' => ['nullable', 'string', 'max:150'],
            'contact.role' => ['nullable', 'string', 'max:100'],
            'contact.email' => ['nullable', 'email', 'max:200'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
