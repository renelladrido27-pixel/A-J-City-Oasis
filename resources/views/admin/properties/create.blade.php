@extends('layouts.admin')

@section('title', 'Add Property')

@section('content')
<x-page-header title="Add Property" />

<div class="card border-0 shadow-sm" style="max-width: 560px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.properties.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address') }}" required>
            </div>

            <hr class="my-4">
            <h2 class="h6 mb-3"><i class="bi bi-door-closed me-1"></i>Rooms (optional)</h2>
            <p class="text-muted small">Automatically create rooms for this property now, numbered by floor (101, 102… 201, 202…). You can add more later from the Rooms page.</p>

            <div class="row g-3 mb-3">
                <div class="col">
                    <label class="form-label">Number of Rooms</label>
                    <input type="number" name="room_count" class="form-control @error('room_count') is-invalid @enderror" value="{{ old('room_count', 0) }}" min="0" max="200">
                    @error('room_count')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col">
                    <label class="form-label">Monthly Rate (per room)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" step="0.01" name="monthly_rate" class="form-control @error('monthly_rate') is-invalid @enderror" value="{{ old('monthly_rate') }}">
                    </div>
                    @error('monthly_rate')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                <a href="{{ route('admin.properties.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
