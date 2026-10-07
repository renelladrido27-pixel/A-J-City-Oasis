@extends('layouts.admin')

@section('title', 'Rooms')

@section('content')
<x-page-header title="Rooms">
    <x-slot:actions>
        <a href="{{ route('admin.rooms.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Room</a>
    </x-slot:actions>
</x-page-header>

<form method="GET" action="{{ route('admin.rooms.index') }}" class="mb-3">
    <div class="d-flex align-items-center gap-2" style="max-width: 320px;">
        <label for="property_id" class="form-label mb-0 text-nowrap">Filter: Property</label>
        <select name="property_id" id="property_id" class="form-select" onchange="this.form.submit()">
            <option value="">All Properties</option>
            @foreach ($properties as $property)
                <option value="{{ $property->id }}" @selected(request('property_id') == $property->id)>{{ $property->name }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th></th><th>Room #</th><th>Property</th><th>Floor</th><th>Type</th><th>Rate</th><th>Status</th><th>Tenant</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($rooms as $room)
                    <tr>
                        <td>
                            @if ($room->images->isNotEmpty())
                                <img src="{{ $room->images->first()->url() }}" class="rounded" style="width: 48px; height: 48px; object-fit: cover;" alt="Room {{ $room->room_number }}">
                            @else
                                <div class="rounded bg-light d-flex align-items-center justify-content-center text-muted" style="width: 48px; height: 48px;"><i class="bi bi-image"></i></div>
                            @endif
                        </td>
                        <td class="fw-medium">{{ $room->room_number }}</td>
                        <td>{{ $room->property->name }}</td>
                        <td>{{ $room->floorLabel() }}</td>
                        <td>{{ ucfirst($room->type) }}</td>
                        <td>₱{{ number_format($room->monthly_rate, 2) }}</td>
                        <td><x-status-badge :status="$room->status" /></td>
                        <td>
                            @if ($lease = $room->activeLease)
                                <a href="{{ route('admin.tenants.show', $lease->tenant) }}" class="fw-medium text-decoration-none">{{ $lease->tenant->name }}</a>
                                <div class="small text-muted">Since {{ $lease->start_date->format('M d, Y') }}</div>
                            @elseif ($booking = $room->reservingBooking)
                                <a href="{{ route('admin.tenants.show', $booking->tenant) }}" class="fw-medium text-decoration-none">{{ $booking->tenant->name }}</a>
                                <div class="small text-muted">
                                    @if ($booking->status === 'pending_payment')
                                        Reserved &middot; awaiting payment
                                    @elseif ($booking->move_in_date)
                                        Moving in {{ $booking->move_in_date->format('M d, Y') }}
                                    @else
                                        Paid &middot; move-in date not set
                                    @endif
                                </div>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.rooms.edit', $room) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.rooms.destroy', $room) }}" class="d-inline" onsubmit="return confirm('Delete this room?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty-state icon="bi-door-closed" message="No rooms found for this property." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $rooms->links() }}</div>
@endsection
