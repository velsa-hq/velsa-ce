<?php

namespace App\Http\Requests;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead && ($this->user()?->can('update', $lead) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $stages = array_map(fn (LeadStage $s) => $s->value, LeadStage::cases());

        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'venue_id' => ['nullable', Rule::exists(Venue::class, 'id')->whereNotNull('active_at')],
            'name' => ['required', 'string', 'max:200'],
            'stage' => ['required', Rule::in($stages)],
            'estimated_value_dollars' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'probability' => ['required', 'numeric', 'min:0', 'max:1'],
            'expected_close_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:50'],
            'lost_reason' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
