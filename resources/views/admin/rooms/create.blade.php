@extends('layouts.admin')

@section('title', 'Add Room')

@section('content')
<x-page-header title="Add Room" />

<div class="card border-0 shadow-sm" style="max-width: 640px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.rooms.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label">Property</label>
                <select name="property_id" class="form-select" required>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}">{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Room Number</label>
                <input type="text" name="room_number" class="form-control" value="{{ old('room_number') }}" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col">
                    <label class="form-label">Floor</label>
                    <input type="number" name="floor" class="form-control" value="{{ old('floor', 1) }}" min="1" required>
                </div>
                <div class="col">
                    <label class="form-label">Type</label>
                    <input type="text" name="type" class="form-control" value="{{ old('type', 'standard') }}" required>
                </div>
                <div class="col">
                    <label class="form-label">Size (sqm)</label>
                    <input type="number" name="size_sqm" class="form-control @error('size_sqm') is-invalid @enderror" value="{{ old('size_sqm') }}" min="1">
                    @error('size_sqm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Monthly Rate</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" name="monthly_rate" class="form-control" value="{{ old('monthly_rate') }}" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="vacant">Vacant</option>
                    <option value="occupied">Occupied</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="reserved">Reserved</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2">{{ old('description') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Inclusions</label>
                <textarea name="inclusions" class="form-control" rows="4" placeholder="One per line, e.g.&#10;2 Beds&#10;Own Sink &amp; CR&#10;Free Wi-Fi">{{ old('inclusions') }}</textarea>
                <div class="form-text">One inclusion per line — shown as a list on the room's page.</div>
            </div>
            <div class="mb-4">
                <x-photo-upload name="images[]" label="Photos" multiple :max-mb="4" hint="The first photo is used as the room's cover image." />
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                <a href="{{ route('admin.rooms.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
