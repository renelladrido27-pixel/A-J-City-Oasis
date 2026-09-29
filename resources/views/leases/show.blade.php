@extends(auth()->user()->isAdmin() || auth()->user()->isStaff() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Lease Details')

@section('content')
<x-page-header :title="'Lease #' . $lease->id">
    <x-slot:actions>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#leaseAgreementModal">
            <i class="bi bi-file-text me-1"></i>View Lease Agreement
        </button>
    </x-slot:actions>
</x-page-header>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <dl class="row mb-0">
            <dt class="col-sm-3 text-muted fw-normal">Tenant</dt>
            <dd class="col-sm-9">{{ $lease->tenant->name }}</dd>
            <dt class="col-sm-3 text-muted fw-normal">Room</dt>
            <dd class="col-sm-9">{{ $lease->room->room_number }} ({{ $lease->room->property->name }})</dd>
            <dt class="col-sm-3 text-muted fw-normal">Start Date</dt>
            <dd class="col-sm-9">{{ $lease->start_date->format('M d, Y') }}</dd>
            <dt class="col-sm-3 text-muted fw-normal">Status</dt>
            <dd class="col-sm-9"><x-status-badge :status="$lease->status" /></dd>
        </dl>

        @if (auth()->user()->isAdmin())
            <div class="d-flex gap-2 mt-3 pt-3 border-top">
                <form method="POST" action="{{ route('admin.leases.generate-rent', $lease) }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-cash-coin me-1"></i>Generate Next Month's Rent</button>
                </form>
                <a href="{{ route('admin.utility-bills.create', $lease) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-lightning-charge me-1"></i>Encode Utility Bill</a>
            </div>
        @else
            <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                <a href="{{ route('tenant.room-transfers.create', $lease) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left-right me-1"></i>Request Room Transfer</a>
                <a href="{{ route('tenant.move-outs.create', $lease) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-left me-1"></i>Request Move-Out</a>
            </div>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3"><i class="bi bi-credit-card me-1"></i>Payments</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Type</th><th>Amount</th><th>Due</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($lease->payments as $payment)
                        @php
                            $displayStatus = match (true) {
                                $payment->status === 'pending' && $payment->isOverdue() => 'overdue',
                                $payment->status === 'pending' && $payment->isWithinGracePeriod() => 'grace_period',
                                default => $payment->status,
                            };
                        @endphp
                        <tr>
                            <td>{{ ucfirst(str_replace('_', ' ', $payment->type)) }}</td>
                            <td>₱{{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->due_date->format('M d, Y') }}</td>
                            <td><x-status-badge :status="$displayStatus" /></td>
                            <td>
                                @if (! auth()->user()->isAdmin() && $payment->status === 'pending')
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
                        <tr><td colspan="5"><x-empty-state icon="bi-credit-card" message="No payments yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3"><i class="bi bi-lightning-charge me-1"></i>Utility Bills</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Type</th><th>Amount</th><th>Due</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($lease->utilityBills as $bill)
                        <tr>
                            <td>{{ ucfirst($bill->type) }}</td>
                            <td>₱{{ number_format($bill->amount, 2) }}</td>
                            <td>{{ $bill->due_date->format('M d, Y') }}</td>
                            <td><x-status-badge :status="$bill->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state icon="bi-lightning-charge" message="No utility bills yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3"><i class="bi bi-tools me-1"></i>Maintenance Requests</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Category</th><th>Description</th><th>Photo</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($lease->maintenanceRequests as $request)
                        <tr>
                            <td>{{ $request->category }}</td>
                            <td>{{ $request->description }}</td>
                            <td>
                                @if ($request->photoUrl())
                                    <a href="{{ $request->photoUrl() }}" target="_blank">
                                        <img src="{{ $request->photoUrl() }}" alt="Issue photo" class="rounded" style="width: 44px; height: 44px; object-fit: cover;">
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><x-status-badge :status="$request->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state icon="bi-tools" message="No maintenance requests yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3"><i class="bi bi-arrow-left-right me-1"></i>Room Transfers</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>From</th><th>To</th><th>Deposit Adjustment</th><th>Status</th>@if (auth()->user()->isAdmin())<th></th>@endif</tr>
                </thead>
                <tbody>
                    @forelse ($lease->roomTransfers as $transfer)
                        <tr>
                            <td>{{ $transfer->fromRoom->room_number }}</td>
                            <td>{{ $transfer->toRoom->room_number }}</td>
                            <td>₱{{ number_format($transfer->deposit_adjustment, 2) }}</td>
                            <td><x-status-badge :status="$transfer->status" /></td>
                            @if (auth()->user()->isAdmin())
                                <td>
                                    @if ($transfer->status === 'pending')
                                        <form method="POST" action="{{ route('admin.room-transfers.approve', $transfer) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.room-transfers.reject', $transfer) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-danger"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="bi-arrow-left-right" message="No room transfers yet." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($lease->moveOut)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3"><i class="bi bi-box-arrow-left me-1"></i>Move-Out</h2>
            @php($moveOut = $lease->moveOut)
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted fw-normal">Requested Date</dt>
                <dd class="col-sm-8">{{ $moveOut->requested_move_out_date->format('M d, Y') }}</dd>
                <dt class="col-sm-4 text-muted fw-normal">Status</dt>
                <dd class="col-sm-8"><x-status-badge :status="$moveOut->isFinalized() ? $moveOut->refund_status : 'awaiting_inspection'" /></dd>
                @if (! $moveOut->isFinalized())
                    <dt class="col-sm-4 text-muted fw-normal">Estimated Refund</dt>
                    <dd class="col-sm-8 fw-semibold">₱{{ number_format($moveOut->refund_amount, 2) }}
                        <div class="small text-muted fw-normal">Security deposit less unpaid bills. The final amount is set after the room inspection, minus any damages.</div>
                    </dd>
                @else
                    <dt class="col-sm-4 text-muted fw-normal">Security Deposit</dt>
                    <dd class="col-sm-8">₱{{ number_format($moveOut->security_deposit, 2) }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Unused Rent</dt>
                    <dd class="col-sm-8">₱{{ number_format($moveOut->unused_rent_credit, 2) }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Unpaid Bills</dt>
                    <dd class="col-sm-8 text-danger">−₱{{ number_format($moveOut->unpaid_dues, 2) }}</dd>
                    @foreach ($moveOut->deductions as $deduction)
                        <dt class="col-sm-4 text-muted fw-normal">{{ $deduction->description }}</dt>
                        <dd class="col-sm-8 text-danger">−₱{{ number_format($deduction->amount, 2) }}</dd>
                    @endforeach
                    @if ($moveOut->balance_due > 0)
                        <dt class="col-sm-4 text-muted fw-normal">Balance Due</dt>
                        <dd class="col-sm-8 fw-semibold">₱{{ number_format($moveOut->balance_due, 2) }}
                            @if (auth()->user()->isTenant() && $moveOut->balancePayment?->status === 'pending')
                                <form method="POST" action="{{ route('payments.pay', $moveOut->balancePayment) }}" class="d-inline ms-2">
                                    @csrf
                                    <button class="btn btn-sm btn-success"><i class="bi bi-credit-card me-1"></i>Pay via Xendit</button>
                                </form>
                            @endif
                        </dd>
                    @else
                        <dt class="col-sm-4 text-muted fw-normal">Refund</dt>
                        <dd class="col-sm-8 fw-semibold">₱{{ number_format($moveOut->refund_amount, 2) }}</dd>
                    @endif
                @endif
            </dl>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.move-outs.show', $moveOut) }}" class="btn btn-sm {{ $moveOut->isFinalized() ? 'btn-outline-secondary' : 'btn-primary' }} mt-3">
                    {{ $moveOut->isFinalized() ? 'View Settlement' : 'Inspect & Finalize' }}
                </a>
            @endif
        </div>
    </div>
@endif

<div class="modal fade" id="leaseAgreementModal" tabindex="-1" aria-labelledby="leaseAgreementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="leaseAgreementModalLabel"><i class="bi bi-file-text me-1"></i>Rental Agreement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @include('partials.lease-agreement', [
                    'room' => $lease->room,
                    'tenantName' => $lease->tenant->name,
                    'startLabel' => $lease->start_date->format('M d, Y'),
                ])
                @if ($lease->booking?->agreement_accepted_at)
                    <p class="text-muted small border-top pt-2 mt-3 mb-0">Agreed to by {{ $lease->tenant->name }} on {{ $lease->booking->agreement_accepted_at->format('M d, Y \a\t g:i A') }}.</p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection
