@extends('layouts.admin')

@section('title', $tenant->name)

@section('content')
<x-page-header :title="$tenant->name" subtitle="Tenant detail, lease agreements, and rental history">
    <x-slot:actions>
        <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Tenants</a>
        <a href="{{ route('admin.users.edit', $tenant) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Edit Account</a>
    </x-slot:actions>
</x-page-header>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <dl class="row mb-0">
            <dt class="col-sm-3 text-muted fw-normal">Email</dt>
            <dd class="col-sm-9">{{ $tenant->email }}</dd>
            <dt class="col-sm-3 text-muted fw-normal">Phone</dt>
            <dd class="col-sm-9">{{ $tenant->phone ?? '—' }}</dd>
            <dt class="col-sm-3 text-muted fw-normal">Account Status</dt>
            <dd class="col-sm-9"><x-status-badge :status="$tenant->is_active ? 'active' : 'deactivated'" /></dd>
        </dl>
    </div>
</div>

<h2 class="h5 mb-3">Rental History</h2>

@forelse ($tenant->leases as $lease)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h3 class="h6 mb-1">
                        <a href="{{ route('admin.leases.show', $lease) }}" class="text-decoration-none">Room {{ $lease->room->room_number }} ({{ $lease->room->property->name }})</a>
                    </h3>
                    <p class="text-muted small mb-0">Started {{ $lease->start_date->format('M d, Y') }}@if ($lease->end_date) &middot; Ended {{ $lease->end_date->format('M d, Y') }}@endif</p>
                </div>
                <x-status-badge :status="$lease->status" />
            </div>

            <div class="border rounded p-3 mb-3 bg-light">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span class="fw-semibold small text-uppercase text-muted">Lease Agreement</span>
                        @if ($lease->document)
                            <div><a href="{{ $lease->documentUrl() }}" target="_blank" class="small"><i class="bi bi-file-earmark-check me-1"></i>View uploaded document</a></div>
                        @else
                            <div class="small text-muted">No document uploaded yet.</div>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form method="POST" action="{{ route('admin.leases.document.store', $lease) }}" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                            @csrf
                            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" required>
                            <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-upload me-1"></i>Upload</button>
                        </form>
                        @if ($lease->document)
                            <form method="POST" action="{{ route('admin.leases.document.destroy', $lease) }}" onsubmit="return confirm('Remove the uploaded lease agreement?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
                @error('document')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 small">
                <div class="col-md-4">
                    <span class="text-muted text-uppercase" style="font-size: .75rem;">Payments</span>
                    <p class="mb-0">{{ $lease->payments->count() }} recorded &middot; ₱{{ number_format($lease->payments->where('status', 'paid')->sum('amount'), 2) }} paid</p>
                </div>
                <div class="col-md-4">
                    <span class="text-muted text-uppercase" style="font-size: .75rem;">Utility Bills</span>
                    <p class="mb-0">{{ $lease->utilityBills->count() }} recorded</p>
                </div>
                <div class="col-md-4">
                    <span class="text-muted text-uppercase" style="font-size: .75rem;">Maintenance Requests</span>
                    <p class="mb-0">{{ $lease->maintenanceRequests->count() }} submitted</p>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <x-empty-state icon="bi-file-earmark-text" message="This tenant has no leases yet." />
        </div>
    </div>
@endforelse
@endsection
