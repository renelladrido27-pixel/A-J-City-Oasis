@extends('layouts.admin')

@section('title', 'Transfer Requests')

@section('content')
<x-page-header title="Transfer Requests" subtitle="Approve or reject tenant room transfer requests" />

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tenant</th><th>From</th><th>To</th><th>Deposits Held</th><th>Deposit Difference</th><th>Preferred Date</th><th>Reason</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($transfers as $transfer)
                    <tr>
                        <td class="fw-medium">{{ $transfer->lease->tenant->name }}</td>
                        <td>Room {{ $transfer->fromRoom->room_number }} <span class="text-muted small">· {{ $transfer->fromRoom->property?->shortName() }}</span></td>
                        <td>Room {{ $transfer->toRoom->room_number }} <span class="text-muted small">· {{ $transfer->toRoom->property?->shortName() }}</span></td>
                        <td>₱{{ number_format($transfer->depositsHeld(), 2) }}</td>
                        <td>₱{{ number_format($transfer->deposit_adjustment, 2) }}</td>
                        <td>{{ $transfer->preferred_date?->format('M d, Y') ?? '—' }}<div class="text-muted small">Requested {{ $transfer->requested_at->format('M d, Y') }}</div></td>
                        <td class="small" style="max-width: 16rem;">{{ $transfer->reason ?: '—' }}</td>
                        <td>
                            <x-status-badge :status="$transfer->status" />
                            @if ($transfer->rejection_reason)<div class="text-muted small mt-1">{{ $transfer->rejection_reason }}</div>@endif
                        </td>
                        <td class="text-end">
                            @if ($transfer->status === 'pending')
                                @if ($transfer->lease->room_id !== $transfer->from_room_id)
                                    {{-- The tenant has moved since asking: approving would empty the wrong room. --}}
                                    <span class="badge text-bg-warning me-1" title="The tenant is no longer in Room {{ $transfer->fromRoom->room_number }}">Out of date</span>
                                @else
                                    <form method="POST" action="{{ route('admin.room-transfers.approve', $transfer) }}" class="d-inline" onsubmit="return confirm('Approve this transfer?')">
                                        @csrf
                                        <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approve</button>
                                    </form>
                                @endif
                                @include('admin.room-transfers._reject-form')
                            @else
                                <a href="{{ route('admin.leases.show', $transfer->lease) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-text"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty-state icon="bi-arrow-left-right" message="No transfer requests." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $transfers->links() }}</div>
@endsection
