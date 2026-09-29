{{--
    Reusable rental agreement text. Pass:
    - $room (required): the Room being booked/leased
    - $tenantName (optional): defaults to the current user's name
    - $startLabel (optional): how to describe the start date (e.g. "your selected move-in date")
--}}
@php
    $tenantName = $tenantName ?? auth()->user()?->name ?? 'the Tenant';
    $monthly = (float) $room->monthly_rate;
    $upfront = $monthly * 3;
    $startLabel = $startLabel ?? 'the move-in date you select';
@endphp

<div class="lease-agreement-text">
    <p class="text-muted small mb-3">Draft &mdash; A &amp; J OASIS Rental Agreement</p>

    <p><strong>1. Parties.</strong> This agreement is between A &amp; J OASIS, a sole proprietorship owned and operated by Jose Valle ("the Owner"), and <strong>{{ $tenantName }}</strong> ("the Tenant"), for the rental unit described below.</p>

    <p><strong>2. Premises.</strong> Room {{ $room->room_number }}, {{ $room->property->name }}{{ $room->floor ? ', Floor '.$room->floor : '' }}.</p>

    <p><strong>3. Rent.</strong> The monthly rental rate for this unit is <strong>₱{{ number_format($monthly, 2) }}</strong>. Billing begins on {{ $startLabel }}, regardless of the date the Tenant physically moves in.</p>

    <p><strong>4. Upfront Payment.</strong> Before move-in, the Tenant shall pay a total of <strong>₱{{ number_format($upfront, 2) }}</strong>, covering one month's advance, one month's deposit, and one month's security, payable in full through the Xendit payment gateway. The unit is reserved only once this payment is confirmed. The advance and the deposit are applied as rent and are not refundable; only the security deposit is refundable, as described in Section 10.</p>

    <p><strong>5. Move-in Date.</strong> The Tenant must select a move-in date within seven (7) days of booking. If no date is selected within that window, the booking may expire and be released.</p>

    <p><strong>6. Cancellation &amp; Refunds.</strong> The Tenant may cancel this booking at any time before the scheduled move-in date for a full refund of the upfront payment, processed through Xendit. No refund is issued for cancellations made on or after the move-in date.</p>

    <p><strong>7. Grace Period.</strong> Both rent and utility payments carry a one-month grace period before an account is flagged as overdue, in consideration of the security deposit already held.</p>

    <p><strong>8. Utility Charges.</strong> Water and electricity charges are encoded by the Owner's administrator at least ten (10) days before their due date and are billed separately from rent.</p>

    <p><strong>9. Room Transfers.</strong> Should the Tenant request a transfer to a different unit, any increase in the required deposit pool (based on three months of the new unit's rate) must be settled by the Tenant before the transfer is approved. If the new unit costs less, any excess is credited or refunded manually by the Owner, outside the system.</p>

    <p><strong>10. Move-Out and Security Deposit.</strong> Upon move-out, the Owner inspects the unit. The security deposit, together with rent already paid for the unused remainder of the month, is applied first to any unpaid bills and then to the cost of any damage found, which is itemized for the Tenant. Any remaining amount is refunded, calculated by the system but disbursed manually by the Owner outside the system. If the unpaid bills and damages exceed the security deposit, the Tenant shall pay the difference through the Xendit payment gateway.</p>

    <p><strong>11. Maintenance.</strong> The Tenant may report maintenance concerns for the unit through the tenant portal. The Owner will address reported issues in a reasonable timeframe but is not responsible for damage caused by the Tenant's own negligence.</p>

    <p><strong>12. Acknowledgment.</strong> By proceeding, the Tenant confirms they have read and understood the terms above and agree to be bound by them for the duration of their stay at A &amp; J OASIS.</p>
</div>
