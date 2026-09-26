@extends('layouts.app')

@section('title', 'Report Maintenance Issue')

@section('content')
<x-page-header :title="'Report Maintenance Issue — Room ' . $lease->room->room_number" />

<div class="card border-0 shadow-sm" style="max-width: 560px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('tenant.maintenance-requests.store', $lease) }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-control @error('category') is-invalid @enderror" value="{{ old('category') }}" placeholder="e.g. Plumbing, Electrical" required>
                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="4" required>{{ old('description') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Photo <span class="text-muted fw-normal">(optional)</span></label>
                <input type="file" name="photo" accept="image/*" class="form-control @error('photo') is-invalid @enderror">
                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Attach a photo of the issue to help staff assess it faster.</div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit</button>
                <a href="{{ route('tenant.maintenance-requests.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
