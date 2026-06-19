<?php

namespace App\Http\Requests;

use App\Enums\SupportRequestCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categories = array_map(fn (SupportRequestCategory $c) => $c->value, SupportRequestCategory::cases());

        return [
            'category' => ['required', Rule::in($categories)],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            // client-supplied; display hint only, not trusted
            'page_url' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
