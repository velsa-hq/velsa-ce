<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use App\Enums\ClientType;
use App\Models\Booking;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BookingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creatableStatuses = array_map(
            fn (BookingStatus $s) => $s->value,
            [BookingStatus::Inquiry, BookingStatus::Hold, BookingStatus::Tentative, BookingStatus::Definite],
        );

        $clientTypes = array_map(fn (ClientType $t) => $t->value, ClientType::cases());

        // Caller must supply either an existing client_id OR an inline new_client.
        $hasNewClient = is_array($this->input('new_client'))
            && filled($this->input('new_client.name'));

        return [
            'venue_id' => ['required', Rule::exists(Venue::class, 'id')->whereNotNull('active_at')],
            'client_id' => [$hasNewClient ? 'nullable' : 'required', 'integer', 'exists:clients,id'],
            'new_client' => ['nullable', 'array'],
            'new_client.name' => ['nullable', 'string', 'max:200'],
            'new_client.type' => ['nullable', Rule::in($clientTypes)],
            'new_client.email' => ['nullable', 'email', 'max:200'],
            'name' => ['required', 'string', 'max:200'],
            'kind' => ['required', 'string', 'max:50'],
            'status' => ['required', Rule::in($creatableStatuses)],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'attendance_estimate' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'total_dollars' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'spaces' => ['required', 'array', 'min:1'],
            'spaces.*' => ['integer', 'exists:spaces,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $venueId = $this->integer('venue_id');
            $spaceIds = $this->input('spaces', []);

            if ($venueId && is_array($spaceIds) && count($spaceIds) > 0) {
                $valid = Space::query()
                    ->where('venue_id', $venueId)
                    ->whereIn('id', $spaceIds)
                    ->pluck('id')
                    ->all();

                $mismatched = array_diff(array_map('intval', $spaceIds), $valid);
                if (! empty($mismatched)) {
                    $v->errors()->add('spaces', 'Selected spaces must belong to the chosen venue.');
                }

                // selected set must be valid against the venue's space hierarchy
                foreach (Space::validateSubset($valid) as $message) {
                    $v->errors()->add('spaces', $message);
                }
            }

            // exactly one of client_id / new_client.name
            $hasClientId = filled($this->input('client_id'));
            $hasNewClient = is_array($this->input('new_client'))
                && filled($this->input('new_client.name'));

            if ($hasClientId && $hasNewClient) {
                $v->errors()->add('new_client.name', 'Pick an existing client or create a new one - not both.');
            } elseif (! $hasClientId && ! $hasNewClient) {
                $v->errors()->add('client_id', 'Select a client or fill in the new-client name.');
            }

            if ($hasNewClient && blank($this->input('new_client.type'))) {
                $v->errors()->add('new_client.type', 'Pick a client type for the new client.');
            }
        });
    }
}
