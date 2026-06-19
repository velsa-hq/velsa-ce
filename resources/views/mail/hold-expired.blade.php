<x-mail::message>
# Your hold has expired

Hi {{ $ownerName }},

The hold on **{{ $booking->name }}** ({{ $booking->reference }}) reached
its expiration date and has been released, so the space is now open for
others.

@if ($booking->start_at)
- Event date: {{ $booking->start_at->format('M j, Y') }}
@endif
- Venue: {{ $booking->venue?->name ?? '-' }}

If you still want this date, reach out to re-establish a hold or move
straight to a confirmed booking.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
