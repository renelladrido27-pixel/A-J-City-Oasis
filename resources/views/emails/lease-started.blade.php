<x-mail::message>
# Welcome to A & J CITY OASIS

Hi {{ $lease->tenant->name }},

Your lease for **Room {{ $lease->room->room_number }}** ({{ $lease->room->property->name }}) has started on **{{ $lease->start_date->format('F d, Y') }}**.

We're glad to have you with us. You can manage your payments, maintenance requests, and more from your tenant dashboard.

<x-mail::button :url="route('tenant.leases.show', $lease)">
View My Lease
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
