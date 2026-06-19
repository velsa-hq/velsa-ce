<x-mail::message>
# Your exhibitor portal link

Hi {{ $exhibitor->contact_name }},

@if ($eventName)
You've been registered as an exhibitor at **{{ $eventName }}**.
@endif

Use the link below to access your exhibitor portal, where you can
browse the equipment catalog, place orders, and pay your balance.

<x-mail::button :url="$loginUrl">
Sign in to the portal
</x-mail::button>

This link expires in **{{ $expiresInDays }} days** and may only be used
once. If you need a new link, reach out to your event coordinator.

For your records:

- **Company:** {{ $exhibitor->company_name }}
- **Booth:** {{ $exhibitor->booth_assignment ?? '-' }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
