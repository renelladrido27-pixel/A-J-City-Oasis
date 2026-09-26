<x-mail::message>
# Payment Overdue

Hi {{ ($payment->lease?->tenant ?? $payment->booking?->tenant)?->name }},

Your **{{ str_replace('_', ' ', $payment->type) }}** payment of **₱{{ number_format($payment->amount, 2) }}** was due on {{ $payment->due_date->format('F d, Y') }} and is now overdue — the one-month grace period has passed.

Please settle this as soon as possible to avoid further action.

@if ($payment->lease)
<x-mail::button :url="route('tenant.leases.show', $payment->lease)">
View Lease
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
