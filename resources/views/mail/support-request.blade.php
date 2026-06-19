@component('mail::message')
# {{ $category }}: {{ $subject }}

A user submitted a support request from inside Velsa.

- **From:** {{ $fromEmail ?? 'unknown' }}@if($fromName) ({{ $fromName }})@endif
@if($pageUrl)
- **Page:** {{ $pageUrl }}
@endif
@if($appVersion)
- **Version:** {{ $appVersion }}
@endif

---

{{ $body }}

@component('mail::subcopy')
You are receiving this because your address is configured as a support recipient in Velsa's system settings. Manage and close requests under Admin -> Support requests.
@endcomponent
@endcomponent
