<?php

namespace App\Http\Requests;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    /**
     * New leads may only be created at an open stage; Won/Lost are
     * reached by working a lead, never on creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $openStages = array_values(array_filter(
            array_map(fn (LeadStage $s) => $s->value, LeadStage::cases()),
            fn (string $value) => LeadStage::from($value)->isOpen(),
        ));

        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'venue_id' => ['nullable', Rule::exists(Venue::class, 'id')->whereNotNull('active_at')],
            'name' => ['required', 'string', 'max:200'],
            'stage' => ['required', Rule::in($openStages)],
            'estimated_value_dollars' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'expected_close_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
