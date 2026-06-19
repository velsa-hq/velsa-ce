<x-mail::message>
# {{ $reportTitle }}

Your scheduled report is attached.

- Report: **{{ $reportTitle }}**
- Schedule: {{ $cadence }}

This is an automated delivery. To change recipients or cadence, open the
report in {{ config('app.name') }} and edit its schedule.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
