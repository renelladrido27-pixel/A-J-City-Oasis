@extends('layouts.admin')

@section('title', 'Edit Room')

@section('content')
<x-page-header :title="'Edit Room ' . $room->room_number" />

<div class="card border-0 shadow-sm mb-4" style="max-width: 640px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.rooms.update', $room) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Property</label>
                <select name="property_id" class="form-select" required>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected($property->id === $room->property_id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Room Number</label>
                <input type="text" name="room_number" class="form-control" value="{{ old('room_number', $room->room_number) }}" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col">
                    <label class="form-label">Floor</label>
                    <select name="floor" class="form-select @error('floor') is-invalid @enderror" required>
                        @foreach (\App\Support\Floor::options(max(10, (int) old('floor', $room->floor))) as $number => $label)
                            <option value="{{ $number }}" @selected((int) old('floor', $room->floor) === $number)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col">
                    <label class="form-label">Type</label>
                    <input type="text" name="type" class="form-control" value="{{ old('type', $room->type) }}" required>
                </div>
                <div class="col">
                    <label class="form-label">Size (sqm)</label>
                    <input type="number" name="size_sqm" class="form-control @error('size_sqm') is-invalid @enderror" value="{{ old('size_sqm', $room->size_sqm) }}" min="1">
                    @error('size_sqm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Monthly Rate</label>
                <div class="input-group">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" name="monthly_rate" class="form-control" value="{{ old('monthly_rate', $room->monthly_rate) }}" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    @foreach (['vacant', 'occupied', 'maintenance', 'reserved'] as $status)
                        <option value="{{ $status }}" @selected($room->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2">{{ old('description', $room->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Inclusions</label>
                <textarea name="inclusions" class="form-control" rows="4" placeholder="One per line, e.g.&#10;2 Beds&#10;Own Sink &amp; CR&#10;Free Wi-Fi">{{ old('inclusions', $room->inclusions ? implode("\n", $room->inclusions) : '') }}</textarea>
                <div class="form-text">One inclusion per line — shown as a list on the room's page.</div>
            </div>
            <div class="mb-4">
                <x-photo-upload name="images[]" label="Add Photos" multiple :max-mb="4" hint="New photos are added to the gallery below — existing ones aren't replaced." />
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                <a href="{{ route('admin.rooms.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width: 640px;">
    <div class="card-body p-4">
        <h2 class="h6 mb-3">Photos</h2>
        @if ($room->images->isEmpty())
            <x-empty-state icon="bi-image" message="No photos uploaded yet." />
        @else
            <div class="row g-3">
                @foreach ($room->images as $image)
                    <div class="col-4">
                        <div class="position-relative">
                            <img src="{{ $image->url() }}" class="img-fluid rounded" style="aspect-ratio: 1; object-fit: cover; width: 100%;" alt="Room {{ $room->room_number }} photo">
                            <form method="POST" action="{{ route('admin.rooms.images.destroy', [$room, $image]) }}" class="position-absolute top-0 end-0 m-1" onsubmit="return confirm('Remove this photo?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger py-0 px-1"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
