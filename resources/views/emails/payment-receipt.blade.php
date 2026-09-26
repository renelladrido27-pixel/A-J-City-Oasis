<x-mail::message>
# Payment Received

Hi {{ ($payment->lease?->tenant ?? $payment->booking?->tenant)?->name }},

We've received your **{{ str_replace('_', ' ', $payment->type) }}** payment.

<x-mail::table>
| | |
|:---|---:|
| Amount | ₱{{ number_format($payment->amount, 2) }} |
| Due Date | {{ $payment->due_date->format('F d, Y') }} |
| Paid On | {{ $payment->paid_at?->format('F d, Y') }} |
</x-mail::table>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
