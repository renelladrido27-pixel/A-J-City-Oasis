<?php

namespace App\Support;

use App\Models\Room;

/**
 * The Terms and Conditions (Rental Agreement) a tenant agrees to before paying.
 * One source for the website (partials/lease-agreement) and the app
 * (GET /api/rooms/{room}/terms), so both show the same words.
 */
class RentalAgreement
{
    public const TITLE = 'Terms and Conditions';

    /** @return list<array{heading: string, body: string}> */
    public static function sections(Room $room, string $tenantName = 'the Tenant', string $startLabel = 'the move-in date you select'): array
    {
        $room->loadMissing('property');
        $monthly = '₱'.number_format((float) $room->monthly_rate, 2);
        $upfront = '₱'.number_format((float) $room->monthly_rate * 3, 2);
        $premises = 'Room '.$room->room_number.', '.$room->property->name
            .($room->floor ? ', '.$room->floorLabel() : '')
            .($room->property->address ? ' ('.$room->property->address.')' : '');

        return array_map(fn ($s) => ['heading' => $s[0], 'body' => $s[1]], [
            ['Parties', "This agreement is between A&J CITY OASIS, a sole proprietorship owned and operated by Jose Valle (\"the Owner\"), and {$tenantName} (\"the Tenant\"), for the rental unit described below."],
            ['Premises', $premises.'.'],
            ['Rent', "The monthly rental rate for this unit is {$monthly}. Billing begins on {$startLabel}, regardless of the date the Tenant physically moves in. Rent then falls due every month on that same day (for example, a move-in on October 27 means rent is due November 27, December 27, and so on; in a shorter month, on its last day). The one month's advance covers the first month."],
            ['Upfront Payment', "Before move-in, the Tenant shall pay a total of {$upfront}, covering one month's advance, one month's deposit, and one month's security, payable in full through the Xendit payment gateway. The unit is reserved only once this payment is confirmed. The advance and the deposit are applied as rent and are not refundable; only the security deposit is refundable, as described in Section 10."],
            ['Move-in Date', 'The Tenant must select a move-in date within seven (7) days of booking. If no date is selected within that window, the booking may expire and be released.'],
            ['Cancellation & Refunds', 'The Tenant may cancel this booking at any time before the scheduled move-in date for a full refund of the upfront payment, processed through Xendit. No refund is issued for cancellations made on or after the move-in date.'],
            ['Grace Period', 'Both rent and utility payments carry a one-month grace period before an account is flagged as overdue, in consideration of the security deposit already held.'],
            ['Utility Charges', "Water and electricity charges are encoded by the Owner's administrator at least ten (10) days before their due date and are billed separately from rent."],
            ['Room Transfers', "Should the Tenant request a transfer to a different unit, any increase in the required deposit pool (based on three months of the new unit's rate) must be settled by the Tenant before the transfer is approved. If the new unit costs less, any excess is credited or refunded manually by the Owner, outside the system."],
            ['Move-Out and Security Deposit', 'Upon move-out, the Owner inspects the unit. The security deposit, together with rent already paid for the unused remainder of the month, is applied first to any unpaid bills and then to the cost of any damage found, which is itemized for the Tenant. Any remaining amount is refunded, calculated by the system but disbursed manually by the Owner outside the system. If the unpaid bills and damages exceed the security deposit, the Tenant shall pay the difference through the Xendit payment gateway.'],
            ['Maintenance', "The Tenant may report maintenance concerns for the unit through the tenant portal or the app. Once the concern is assigned, the Tenant confirms when the work is done. The Owner will address reported issues in a reasonable timeframe but is not responsible for damage caused by the Tenant's own negligence."],
            ['Acknowledgment', 'By proceeding to payment, the Tenant confirms they have read and understood the terms above and agree to be bound by them for the duration of their stay at A&J CITY OASIS.'],
        ]);
    }
}
