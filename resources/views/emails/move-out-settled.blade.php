<x-mail::message>
# Move-Out Settlement

Hi {{ $moveOut->lease->tenant->name }},

Your move-out from **Room {{ $moveOut->lease->room->room_number }}** was finalized on {{ $moveOut->actual_move_out_date->format('F d, Y') }}. Here is how your deposits were settled:

<x-mail::table>
| | |
|:---|---:|
| 1-month deposit | ₱{{ number_format($moveOut->rent_deposit, 2) }} |
| Security deposit | ₱{{ number_format($moveOut->security_deposit, 2) }} |
| Less: unpaid bills | −₱{{ number_format($moveOut->unpaid_dues, 2) }} |
@foreach ($moveOut->deductions as $deduction)
| Less: {{ $deduction->description }} | −₱{{ number_format($deduction->amount, 2) }} |
@endforeach
@if ($moveOut->refund_status === 'balance_due')
| **Balance you owe** | **₱{{ number_format($moveOut->balance_due, 2) }}** |
@else
| **Refund to you** | **₱{{ number_format($moveOut->refund_amount, 2) }}** |
@endif
</x-mail::table>

@if ($moveOut->refund_status === 'balance_due')
The deductions exceeded your deposits. Please settle the balance of **₱{{ number_format($moveOut->balance_due, 2) }}** directly with the A&J CITY OASIS admin — it is not paid through the website or the app.
@elseif ($moveOut->refund_amount > 0)
Your refund will be released to you by the admin.
@endif

Thank you for staying with us,<br>
{{ config('app.name') }}
</x-mail::message>
