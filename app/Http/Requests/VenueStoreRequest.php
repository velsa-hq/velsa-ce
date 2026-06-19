<?php

namespace App\Http\Requests;

use App\Models\Venue;
use App\Support\DisplayImage;
use Illuminate\Foundation\Http\FormRequest;

class VenueStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Venue::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'building' => ['nullable', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'url:http,https', 'max:255'],
            'timezone' => ['required', 'string', 'max:50'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'enforce_setup_buffers' => ['boolean'],
            ...DisplayImage::rules(),
        ];
    }
}
