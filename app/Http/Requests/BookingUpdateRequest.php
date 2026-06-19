<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BookingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking && ($this->user()?->can('update', $booking) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_map(fn (BookingStatus $s) => $s->value, BookingStatus::cases());

        return [
            'venue_id' => ['required', Rule::exists(Venue::class, 'id')->whereNotNull('active_at')],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:200'],
            'kind' => ['required', 'string', 'max:50'],
            'status' => ['required', Rule::in($statuses)],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'attendance_estimate' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'total_dollars' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'cancel_reason' => ['nullable', 'string', 'max:500'],
            'spaces' => ['required', 'array', 'min:1'],
            'spaces.*' => ['integer', 'exists:spaces,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $venueId = $this->integer('venue_id');
            $spaceIds = $this->input('spaces', []);

            if (! $venueId || ! is_array($spaceIds) || count($spaceIds) === 0) {
                return;
            }

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
        });
    }
}
