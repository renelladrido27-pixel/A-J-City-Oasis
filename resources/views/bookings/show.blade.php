@extends('layouts.app')

@section('title', 'Booking Details')

@section('content')
<x-page-header :title="'Booking #' . $booking->id" />

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <dl class="row mb-0">
            <dt class="col-sm-4 text-muted fw-normal">Room</dt>
            <dd class="col-sm-8">{{ $booking->room->room_number }} ({{ $booking->room->property->name }})</dd>
            <dt class="col-sm-4 text-muted fw-normal">Status</dt>
            <dd class="col-sm-8"><x-status-badge :status="$booking->status" /></dd>
            <dt class="col-sm-4 text-muted fw-normal">Move-in Deadline</dt>
            <dd class="col-sm-8">{{ $booking->move_in_deadline->format('M d, Y') }}</dd>
            <dt class="col-sm-4 text-muted fw-normal">Move-in Date</dt>
            <dd class="col-sm-8">{{ $booking->move_in_date?->format('M d, Y') ?? 'Not yet selected' }}</dd>
            <dt class="col-sm-4 text-muted fw-normal">Total Upfront</dt>
            <dd class="col-sm-8 fw-semibold">₱{{ number_format($booking->total_amount, 2) }} <span class="fw-normal text-muted">(Advance + Deposit + Security)</span></dd>
        </dl>
    </div>
</div>

@if (! $booking->move_in_date && $booking->status !== 'cancelled' && $booking->status !== 'expired')
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3"><i class="bi bi-calendar-event me-1"></i>Select Move-in Date</h2>
            <form method="POST" action="{{ route('tenant.bookings.move-in-date', $booking) }}" class="d-flex gap-2">
                @csrf @method('PUT')
                <input type="date" name="move_in_date" class="form-control" min="{{ now()->toDateString() }}" max="{{ $booking->move_in_deadline->toDateString() }}" required>
                <button class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3"><i class="bi bi-credit-card me-1"></i>Payments</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Type</th><th>Amount</th><th>Due</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($booking->payments as $payment)
                        <tr>
                            <td>{{ ucfirst(str_replace('_', ' ', $payment->type)) }}</td>
                            <td>₱{{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->due_date->format('M d, Y') }}</td>
                            <td><x-status-badge :status="$payment->status" /></td>
                            <td>
                                @if ($payment->status === 'pending')
                                    <form method="POST" action="{{ route('payments.pay', $payment) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success"><i class="bi bi-credit-card me-1"></i>Pay via Xendit</button>
                                    </form>
                                    @if ($payment->xendit_invoice_id && ! config('xendit.fake_mode'))
                                        <form method="POST" action="{{ route('payments.check-status', $payment) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary" title="Already paid on Xendit's checkout page? Check here for local dev, since the webhook can't reach 127.0.0.1."><i class="bi bi-arrow-repeat me-1"></i>Check Status</button>
                                        </form>
                                    @endif
                                @endif
                                @if ($payment->status === 'paid')
                                    <a href="{{ route('payments.receipt', $payment) }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-receipt me-1"></i>Receipt</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="bi-credit-card" message="No payments yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($booking->isCancellableWithFullRefund())
    <form method="POST" action="{{ route('tenant.bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking? You will receive a full refund.')">
        @csrf
        <button class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Cancel Booking (Full Refund)</button>
    </form>
@endif
@endsection
