@extends('layouts.admin')

@section('title', 'Encode Utility Bill')

@section('content')
<x-page-header :title="'Encode Utility Bill — Lease #' . $lease->id" />

<div class="alert alert-info d-flex align-items-start small">
    <i class="bi bi-info-circle-fill me-2 mt-1"></i>
    <div>Due date must be at least 10 days from today ({{ now()->addDays(10)->toDateString() }} or later).</div>
</div>

<div class="card border-0 shadow-sm" style="max-width: 560px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.utility-bills.store', $lease) }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                    <option value="water" @selected(old('type') === 'water')>Water</option>
                    <option value="electricity" @selected(old('type') === 'electricity')>Electricity</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Amount</label>
                <div class="input-group has-validation">
                    <span class="input-group-text">₱</span>
                    <input type="number" step="0.01" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date') }}" min="{{ now()->addDays(10)->toDateString() }}" required>
                @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <x-photo-upload name="receipt_photo" label="Receipt photo" hint="Photo of the water/electric provider's bill, so the tenant can verify the amount." />
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                <a href="{{ route('admin.utility-bills.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
