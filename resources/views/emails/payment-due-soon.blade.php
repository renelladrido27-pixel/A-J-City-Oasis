<x-mail::message>
# Payment Reminder

Hi {{ $payment->lease?->tenant?->name }},

Your **{{ str_replace('_', ' ', $payment->type) }}** payment of **₱{{ number_format($payment->amount, 2) }}**@if ($payment->lease?->room) for Room {{ $payment->lease->room->room_number }}@endif is due **{{ $when }}**, on {{ $payment->due_date->format('F j, Y') }}.

You can pay online any time before then — on the website or in the app.

<x-mail::button :url="route('tenant.payments.index')">
Pay Now
</x-mail::button>

Already paid? You can ignore this message.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
