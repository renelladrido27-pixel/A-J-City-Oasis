@extends(auth()->user()->isAdmin() || auth()->user()->isStaff() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Payments')

@section('content')
<x-page-header :title="auth()->user()->isAdmin() ? 'All Payments' : 'My Payments'" />

@if (! auth()->user()->isAdmin())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4 d-flex flex-wrap gap-4">
            <div>
                <p class="text-muted small text-uppercase mb-1">Outstanding Balance</p>
                <p class="h3 mb-0 {{ $outstandingBalance > 0 ? 'text-danger' : 'text-success' }}">₱{{ number_format($outstandingBalance, 2) }}</p>
            </div>
            <div>
                <p class="text-muted small text-uppercase mb-1">Next Due Date</p>
                <p class="h3 mb-0">{{ $nextDueDate?->format('M d, Y') ?? '—' }}</p>
            </div>
        </div>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Type</th><th>Reference</th><th>Amount</th><th>Due</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    @php
                        $displayStatus = match (true) {
                            $payment->status === 'pending' && $payment->isOverdue() => 'overdue',
                            $payment->status === 'pending' && $payment->isWithinGracePeriod() => 'grace_period',
                            default => $payment->status,
                        };
                    @endphp
                    <tr>
                        <td>{{ ucfirst(str_replace('_', ' ', $payment->type)) }}</td>
                        <td class="text-muted">
                            @if ($payment->lease) Lease #{{ $payment->lease_id }} @elseif ($payment->booking) Booking #{{ $payment->booking_id }} @endif
                        </td>
                        <td>₱{{ number_format($payment->amount, 2) }}</td>
                        <td>{{ $payment->due_date->format('M d, Y') }}</td>
                        <td><x-status-badge :status="$displayStatus" /></td>
                        <td class="text-end">
                            @if ($receiptUrl = $payment->utilityBill?->receiptUrl())
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#receiptModal"
                                        data-receipt-url="{{ $receiptUrl }}" data-receipt-title="{{ ucfirst($payment->utilityBill->type) }} bill — due {{ $payment->due_date->format('M d, Y') }}">
                                    <i class="bi bi-image me-1"></i>Bill photo
                                </button>
                            @endif
                            @if (! auth()->user()->isAdmin() && $payment->status === 'pending')
                                <form method="POST" action="{{ route('payments.pay', $payment) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-success" data-loading-text="Opening payment…"><i class="bi bi-credit-card me-1"></i>Pay</button>
                                </form>
                                @if ($payment->xendit_invoice_id && ! config('xendit.fake_mode'))
                                    <form method="POST" action="{{ route('payments.check-status', $payment) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" title="Already paid on Xendit's checkout page? Check here for local dev, since the webhook can't reach 127.0.0.1."><i class="bi bi-arrow-repeat me-1"></i>Check Status</button>
                                    </form>
                                @endif
                            @endif
                            @if (auth()->user()->isAdmin() && $payment->status === 'pending')
                                <form method="POST" action="{{ route('admin.payments.record-manual', $payment) }}" class="d-inline" onsubmit="return confirm('Record this payment as paid via cash/walk-in? This cannot be undone.')">
                                    @csrf
                                    <button class="btn btn-sm btn-success"><i class="bi bi-cash-coin me-1"></i>Record Payment</button>
                                </form>
                            @endif
                            @if ($payment->status === 'paid')
                                <a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-receipt me-1"></i>Receipt</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="bi-credit-card" message="No payments yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $payments->links() }}</div>

@include('partials.receipt-photo-modal')
@endsection
