@extends('layouts.admin')

@section('title', 'Edit Property')

@section('content')
<x-page-header title="Edit Property" />

<div class="card border-0 shadow-sm" style="max-width: 720px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.properties.update', $property) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $property->name) }}" required autofocus>
            </div>

            <h2 class="h6 mt-4 mb-3"><i class="bi bi-geo-alt me-1"></i>Address</h2>
            <x-address-fields :property="$property" />

            <p class="text-muted small mt-3"><i class="bi bi-door-closed me-1"></i>This building has <strong>{{ $property->rooms()->count() }}</strong> room(s). Edit each room from the Rooms page.</p>

            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                <a href="{{ route('admin.properties.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
