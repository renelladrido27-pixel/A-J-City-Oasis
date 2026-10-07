<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }} — A &amp; J CITY OASIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f5f7; }
        .receipt-card { max-width: 640px; margin: 2.5rem auto; }
        .receipt-header { border-bottom: 2px solid #198754; }
        .amount-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: .5rem; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .receipt-card { margin: 0; max-width: 100%; }
            .card { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center no-print pt-4" style="max-width: 640px; margin: 0 auto;">
            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
            <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer me-1"></i>Print / Save as PDF</button>
        </div>

        <div class="card receipt-card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-start receipt-header pb-3 mb-4">
                    <div>
                        <h1 class="h4 mb-0"><i class="bi bi-house-door-fill text-success me-1"></i>A &amp; J CITY OASIS</h1>
                        <p class="text-muted small mb-0">Koronadal City, South Cotabato</p>
                    </div>
                    <div class="text-end">
                        <h2 class="h5 mb-0">Receipt</h2>
                        <p class="text-muted small mb-0">#{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</p>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-6">
                        <p class="text-muted small text-uppercase mb-1">Billed To</p>
                        <p class="mb-0 fw-semibold">{{ $payment->lease?->tenant->name ?? $payment->booking?->tenant->name }}</p>
                        <p class="text-muted small mb-0">{{ $payment->lease?->tenant->email ?? $payment->booking?->tenant->email }}</p>
                    </div>
                    <div class="col-6 text-end">
                        <p class="text-muted small text-uppercase mb-1">Room</p>
                        @php($room = $payment->lease?->room ?? $payment->booking?->room)
                        <p class="mb-0 fw-semibold">{{ $room?->room_number }}</p>
                        <p class="text-muted small mb-0">{{ $room?->property->name }}</p>
                    </div>
                </div>

                <table class="table table-borderless mb-4">
                    <thead>
                        <tr class="border-bottom">
                            <th class="text-muted small text-uppercase fw-normal">Description</th>
                            <th class="text-muted small text-uppercase fw-normal text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ ucfirst(str_replace('_', ' ', $payment->type)) }}</td>
                            <td class="text-end">₱{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="amount-box p-3 d-flex justify-content-between align-items-center mb-4">
                    <span class="fw-semibold">Total Paid</span>
                    <span class="h5 mb-0 text-success fw-bold">₱{{ number_format($payment->amount, 2) }}</span>
                </div>

                <div class="row small">
                    <div class="col-6">
                        <p class="text-muted mb-1">Date Paid</p>
                        <p class="fw-semibold">{{ $payment->paid_at?->format('M d, Y g:i A') ?? '—' }}</p>
                    </div>
                    <div class="col-6 text-end">
                        <p class="text-muted mb-1">Payment Reference</p>
                        <p class="fw-semibold text-break">{{ $payment->xendit_payment_id ?? $payment->xendit_invoice_id ?? 'Recorded manually' }}</p>
                    </div>
                </div>

                <div class="text-center border-top pt-3 mt-3">
                    <span class="badge bg-success-subtle text-success-emphasis px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i>Paid in Full</span>
                    <p class="text-muted small mt-3 mb-0">This is a system-generated receipt from A &amp; J CITY OASIS. No signature required.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
