<x-mail::message>
# You're first in line

Hi {{ $ownerName }},

Good news - the hold ahead of yours on **{{ $booking->name }}**
({{ $booking->reference }}) was released, so your hold is now **first in
line**. The space is available for you to confirm.

@if ($booking->start_at)
- Event date: {{ $booking->start_at->format('M j, Y') }}
@endif
- Venue: {{ $booking->venue?->name ?? '-' }}

Confirm the booking soon to secure the date before the hold lapses.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
