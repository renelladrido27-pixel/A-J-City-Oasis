@extends('layouts.admin')

@section('title', 'Transfer Requests')

@section('content')
<x-page-header title="Transfer Requests" subtitle="Approve or reject tenant room transfer requests" />

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tenant</th><th>From</th><th>To</th><th>Deposit Adjustment</th><th>Requested</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($transfers as $transfer)
                    <tr>
                        <td class="fw-medium">{{ $transfer->lease->tenant->name }}</td>
                        <td>Room {{ $transfer->fromRoom->room_number }}</td>
                        <td>Room {{ $transfer->toRoom->room_number }}</td>
                        <td>₱{{ number_format($transfer->deposit_adjustment, 2) }}</td>
                        <td>{{ $transfer->requested_at->format('M d, Y') }}</td>
                        <td><x-status-badge :status="$transfer->status" /></td>
                        <td class="text-end">
                            @if ($transfer->status === 'pending')
                                <form method="POST" action="{{ route('admin.room-transfers.approve', $transfer) }}" class="d-inline" onsubmit="return confirm('Approve this transfer?')">
                                    @csrf
                                    <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.room-transfers.reject', $transfer) }}" class="d-inline" onsubmit="return confirm('Reject this transfer?')">
                                    @csrf
                                    <button class="btn btn-sm btn-danger"><i class="bi bi-x-lg me-1"></i>Reject</button>
                                </form>
                            @else
                                <a href="{{ route('admin.leases.show', $transfer->lease) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-text"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="bi-arrow-left-right" message="No transfer requests." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $transfers->links() }}</div>
@endsection
