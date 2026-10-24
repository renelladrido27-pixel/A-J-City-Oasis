{{--
    The receipt as a PDF (rendered by dompdf — see PaymentController::receiptPdf).
    Same content as payments/receipt.blade.php, but laid out with tables and
    plain CSS: dompdf has no flexbox and doesn't load Bootstrap. DejaVu Sans is
    the bundled font that has the peso sign.
--}}
@php
    $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;
    $room = $payment->lease?->room ?? $payment->booking?->room;
    $number = str_pad($payment->id, 6, '0', STR_PAD_LEFT);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $number }} — A&amp;J CITY OASIS</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2d27; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 0; vertical-align: top; text-align: left; }
        .right { text-align: right; }
        .muted { color: #6b7a72; }
        .caption { color: #6b7a72; font-size: 8.5px; letter-spacing: .5px; text-transform: uppercase; }
        .brand { font-size: 18px; font-weight: bold; color: #1b4332; }
        .header td { padding-bottom: 12px; border-bottom: 2px solid #1b4332; }
        .parties td { padding-top: 18px; }
        .strong { font-weight: bold; font-size: 12px; }
        .lines { margin-top: 22px; }
        .lines th { padding-bottom: 6px; border-bottom: 1px solid #d9dedb; font-weight: normal; }
        .lines td { padding: 8px 0 2px; }
        .note { color: #6b7a72; font-size: 9px; }
        .total { margin-top: 14px; background: #eaf3ee; border: 1px solid #b9d6c5; }
        .total td { padding: 11px 14px; font-weight: bold; vertical-align: middle; }
        .total .amount { font-size: 16px; color: #1b4332; }
        .meta td { padding-top: 20px; }
        .footer { margin-top: 26px; padding-top: 14px; border-top: 1px solid #d9dedb; text-align: center; }
        .paid { display: inline-block; padding: 5px 14px; background: #eaf3ee; color: #1b4332; font-weight: bold; border-radius: 10px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="brand">A&amp;J CITY OASIS</div>
                <div class="muted">Koronadal City, South Cotabato</div>
            </td>
            <td class="right">
                <div class="strong" style="font-size: 14px;">Receipt</div>
                <div class="muted">#{{ $number }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td style="width: 55%;">
                <div class="caption">Billed to</div>
                <div class="strong">{{ $tenant?->name }}</div>
                <div class="muted">{{ $tenant?->email }}</div>
            </td>
            <td class="right">
                <div class="caption">Room</div>
                <div class="strong">{{ $room?->room_number }}</div>
                <div class="muted">{{ $room?->property?->name }}</div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <tr>
            <th class="caption">Description</th>
            <th class="caption right">Amount</th>
        </tr>
        @foreach ($payment->lines() as $line)
            <tr>
                <td>
                    {{ $line['label'] }}
                    @if ($line['note'])
                        <div class="note">{{ $line['note'] }}</div>
                    @endif
                </td>
                <td class="right">₱{{ number_format($line['amount'], 2) }}</td>
            </tr>
        @endforeach
    </table>

    <table class="total">
        <tr>
            <td>Total Paid</td>
            <td class="right amount">₱{{ number_format($payment->amount, 2) }}</td>
        </tr>
    </table>

    @if ($payment->refundNote())
        <p class="note" style="margin-top: 8px;">{{ $payment->refundNote() }}</p>
    @endif

    @if ($due = $payment->receiptDueDate())
        <table class="meta">
            <tr>
                <td>
                    <div class="caption">{{ $due['label'] }}</div>
                    <div class="strong">{{ $due['date']->format('M d, Y') }}</div>
                </td>
            </tr>
        </table>
    @endif

    <table class="meta">
        <tr>
            <td style="width: 45%;">
                <div class="caption">Date paid</div>
                <div class="strong">{{ $payment->paid_at?->format('M d, Y g:i A') ?? '—' }}</div>
            </td>
            <td class="right">
                <div class="caption">Payment reference</div>
                <div class="strong">{{ $payment->xendit_payment_id ?? $payment->xendit_invoice_id ?? 'Recorded manually' }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        <span class="paid">Paid in Full</span>
        <p class="muted" style="margin-top: 12px;">This is a system-generated receipt from A&amp;J CITY OASIS. No signature required.</p>
    </div>
</body>
</html>
