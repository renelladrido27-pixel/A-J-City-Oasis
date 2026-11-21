@extends('layouts.app')

@section('title', 'Request Room Transfer')

@section('content')
<x-page-header title="Request Room Transfer" />

<div class="card border-0 shadow-sm" style="max-width: 560px;">
    <div class="card-body p-4">
        <p class="mb-2">Current room: <strong>Room {{ $lease->room->room_number }}, {{ $lease->room->property->shortName() }}</strong> <span class="text-muted">(₱{{ number_format($lease->room->monthly_rate, 2) }}/month)</span></p>

        <div class="alert alert-info d-flex align-items-start small">
            <i class="bi bi-info-circle-fill me-2 mt-1"></i>
            <div>Upgrading requires paying the deposit difference. Downgrade credits are handled manually by the admin outside the system.</div>
        </div>

        <form method="POST" action="{{ route('tenant.room-transfers.store', $lease) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">New Room</label>
                {{-- Grouped by building, with its address: the same room number exists in more than one building. --}}
                <select name="to_room_id" id="to_room_id" class="form-select @error('to_room_id') is-invalid @enderror" required>
                    <option value="" data-rate="">Choose a room…</option>
                    @foreach ($rooms->groupBy('property_id') as $buildingRooms)
                        @php($building = $buildingRooms->first()->property)
                        <optgroup label="{{ $building->shortName() }} — {{ $building->address }}">
                            @foreach ($buildingRooms as $room)
                                <option value="{{ $room->id }}" data-rate="{{ $room->monthly_rate }}" @selected(old('to_room_id') == $room->id)>
                                    Room {{ $room->room_number }}, {{ $building->shortName() }} · {{ $room->typeLabel() }} · {{ $room->floorLabel() }} — ₱{{ number_format($room->monthly_rate, 2) }}/month
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('to_room_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="preferred_date">Preferred Transfer Date</label>
                <input type="date" name="preferred_date" id="preferred_date" class="form-control @error('preferred_date') is-invalid @enderror" min="{{ today()->toDateString() }}" value="{{ old('preferred_date') }}" required>
                @error('preferred_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="reason">Reason for Transfer</label>
                <textarea name="reason" id="reason" rows="3" maxlength="2000" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            {{-- Same formula as RoomTransferController::store(): new rate − current rate. --}}
            <p class="mb-3 small" id="deposit-difference" aria-live="polite">Choose a room to see the deposit difference.</p>
            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-arrow-left-right me-1"></i>Request Transfer</button>
                <a href="{{ route('tenant.leases.show', $lease) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
    (() => {
        const select = document.getElementById('to_room_id');
        const out = document.getElementById('deposit-difference');
        const current = {{ (float) $lease->room->monthly_rate }};
        const peso = n => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const show = () => {
            const rate = parseFloat(select.selectedOptions[0]?.dataset.rate);
            if (isNaN(rate)) { out.textContent = 'Choose a room to see the deposit difference.'; return; }
            const diff = rate - current;
            out.innerHTML = diff > 0
                ? `Deposit difference: <strong>${peso(diff)}</strong> to pay before the admin can approve.`
                : diff < 0
                    ? `Deposit difference: <strong>${peso(-diff)}</strong> credit, handled by the admin outside the system.`
                    : 'Deposit difference: <strong>₱0.00</strong> — same monthly rate.';
        };
        select.addEventListener('change', show);
        show();
    })();
</script>
@endsection
