@extends('layouts.app')

@section('title', 'Request Room Transfer')

@section('content')
<x-page-header title="Request Room Transfer" />

<div class="card border-0 shadow-sm" style="max-width: 560px;">
    <div class="card-body p-4">
        <p class="mb-2">Current room: <strong>{{ $lease->room->room_number }}</strong> <span class="text-muted">(₱{{ number_format($lease->room->monthly_rate, 2) }}/month)</span></p>

        <div class="alert alert-info d-flex align-items-start small">
            <i class="bi bi-info-circle-fill me-2 mt-1"></i>
            <div>Upgrading requires paying the deposit difference. Downgrade credits are handled manually by the admin outside the system.</div>
        </div>

        <form method="POST" action="{{ route('tenant.room-transfers.store', $lease) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">New Room</label>
                <select name="to_room_id" class="form-select" required>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}">
                            {{ $room->room_number }} ({{ $room->property->name }}) — ₱{{ number_format($room->monthly_rate, 2) }}/month
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-arrow-left-right me-1"></i>Request Transfer</button>
                <a href="{{ route('tenant.leases.show', $lease) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
