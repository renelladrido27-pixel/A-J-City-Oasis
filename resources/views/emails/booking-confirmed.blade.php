<x-mail::message>
# Booking Confirmed

Hi {{ $booking->tenant->name }},

Your upfront payment was received and your booking for **Room {{ $booking->room->room_number }}** ({{ $booking->room->property->name }}) is now confirmed.

<x-mail::table>
| | |
|:---|---:|
| Advance | ₱{{ number_format($booking->advance_amount, 2) }} |
| Deposit | ₱{{ number_format($booking->deposit_amount, 2) }} |
| Security | ₱{{ number_format($booking->security_amount, 2) }} |
| **Total Paid** | **₱{{ number_format($booking->total_amount, 2) }}** |
</x-mail::table>

@if ($booking->move_in_date)
Your move-in date is **{{ $booking->move_in_date->format('F d, Y') }}**. Billing starts on this date regardless of when you physically arrive.
@else
Please select your move-in date within 7 days of booking (by **{{ $booking->move_in_deadline->format('F d, Y') }}**).

<x-mail::button :url="route('tenant.bookings.show', $booking)">
Select Move-in Date
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
