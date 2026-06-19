@component('mail::message')
# Insurance certificates expiring soon

The following certificate(s) expire within the next {{ $reminderDays }} days. Review them and chase a renewal where needed.

@component('mail::table')
| Holder | Policy | Carrier | Expires |
|:-------|:-------|:--------|:--------|
@foreach($rows as $row)
| {{ $row['holder'] }} | {{ $row['policy_type'] }} | {{ $row['carrier'] ?? '-' }} | {{ $row['expires_on'] }} |
@endforeach
@endcomponent

@component('mail::button', ['url' => route('admin.insurance-certificates.index', ['status' => 'expiring'])])
Review certificates
@endcomponent

@component('mail::subcopy')
You are receiving this because your address is configured as a compliance contact in Velsa's system settings.
@endcomponent
@endcomponent
