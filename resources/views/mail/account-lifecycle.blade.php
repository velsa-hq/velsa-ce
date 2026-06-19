@component('mail::message')
# Account {{ $action }}

A user account was **{{ $action }}** on Velsa.

- **Account:** {{ $accountEmail }}@if($accountName) ({{ $accountName }})@endif
- **Performed by:** {{ $actorEmail ?? 'the account holder / system' }}
- **When:** {{ $occurredAt }}
@if($ip)
- **Source IP:** {{ $ip }}
@endif

This is an automated security notification (NIST AC-2). Review the audit log for full detail.

@component('mail::subcopy')
You are receiving this because your address is configured as a security notification recipient in Velsa's system settings.
@endcomponent
@endcomponent
