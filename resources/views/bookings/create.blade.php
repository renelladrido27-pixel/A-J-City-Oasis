@extends('layouts.app')

@section('title', 'Book Room')

@section('content')
<x-page-header :title="'Book Room ' . $room->room_number">
    <x-slot:actions>
        <a href="{{ route('rooms.show', $room) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>View Full Details</a>
    </x-slot:actions>
</x-page-header>

<div class="card border-0 shadow-sm" style="max-width: 560px;">
    <div class="card-body p-4">
        <h2 class="h6 text-muted mb-3">{{ $room->property->name }} &middot; Floor {{ $room->floor }} &middot; {{ ucfirst($room->type) }}</h2>

        <dl class="row mb-3">
            <dt class="col-6 fw-normal text-muted">Monthly rate</dt>
            <dd class="col-6 text-end">₱{{ number_format($room->monthly_rate, 2) }}</dd>
            <dt class="col-6 fw-normal text-muted">Upfront payment (advance + deposit + security)</dt>
            <dd class="col-6 text-end fw-semibold">₱{{ number_format($room->monthly_rate * 3, 2) }}</dd>
        </dl>

        <div class="alert alert-info d-flex align-items-start small">
            <i class="bi bi-info-circle-fill me-2 mt-1"></i>
            <div>You'll have 7 days from booking to confirm your move-in date. You can also select it now.</div>
        </div>

        <h2 class="h6 mb-2">Rental Agreement</h2>
        <div id="agreementBox" class="border rounded p-3 mb-2 bg-light" style="max-height: 260px; overflow-y: auto;">
            @include('partials.lease-agreement', ['room' => $room])
        </div>
        <p id="agreementHint" class="text-muted small mb-3"><i class="bi bi-arrow-down-circle me-1"></i>Scroll to the bottom to continue.</p>

        <form method="POST" action="{{ route('tenant.bookings.store', $room) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Move-in Date (optional now)</label>
                <input type="date" name="move_in_date" class="form-control @error('move_in_date') is-invalid @enderror" min="{{ now()->toDateString() }}" max="{{ now()->addDays(7)->toDateString() }}" value="{{ old('move_in_date') }}">
                @error('move_in_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Must be within 7 days of booking. You can also select it later, up until the same 7-day deadline.</div>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="agreed_to_terms" value="1" class="form-check-input" id="agreeCheckbox" disabled required>
                <label class="form-check-label" for="agreeCheckbox">I have read and agree to the Rental Agreement above.</label>
                @error('agreed_to_terms')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <button class="btn btn-primary w-100" data-loading-text="Opening payment…"><i class="bi bi-credit-card me-1"></i>Confirm &amp; Pay</button>
        </form>
    </div>
</div>

<script>
    (function () {
        const box = document.getElementById('agreementBox');
        const checkbox = document.getElementById('agreeCheckbox');
        const hint = document.getElementById('agreementHint');

        function unlock() {
            checkbox.disabled = false;
            hint.classList.add('d-none');
        }

        box.addEventListener('scroll', function () {
            if (box.scrollTop + box.clientHeight >= box.scrollHeight - 4) {
                unlock();
            }
        });

        // If the agreement is short enough to not need scrolling, unlock immediately.
        if (box.scrollHeight <= box.clientHeight) {
            unlock();
        }
    })();
</script>
@endsection
