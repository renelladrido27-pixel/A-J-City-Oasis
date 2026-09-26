<x-mail::message>
# New Utility Bill

Hi {{ $utilityBill->lease->tenant->name }},

A new **{{ $utilityBill->type }}** bill has been added to your account for Room {{ $utilityBill->lease->room->room_number }}.

<x-mail::table>
| | |
|:---|---:|
| Amount | ₱{{ number_format($utilityBill->amount, 2) }} |
| Due Date | {{ $utilityBill->due_date->format('F d, Y') }} |
</x-mail::table>

<x-mail::button :url="route('tenant.leases.show', $utilityBill->lease)">
View &amp; Pay
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
