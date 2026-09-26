@extends('layouts.app')

@section('title', 'Request Move-Out')

@section('content')
<x-page-header title="Request Move-Out" />

<div class="alert alert-info d-flex align-items-start small">
    <i class="bi bi-info-circle-fill me-2 mt-1"></i>
    <div>Your refund will be calculated automatically by the system. Disbursement is handled manually by the admin.</div>
</div>

<div class="card border-0 shadow-sm" style="max-width: 560px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('tenant.move-outs.store', $lease) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Requested Move-Out Date</label>
                <input type="date" name="requested_move_out_date" class="form-control" min="{{ now()->toDateString() }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Reason (optional)</label>
                <textarea name="reason" class="form-control" rows="3"></textarea>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-box-arrow-left me-1"></i>Submit Request</button>
                <a href="{{ route('tenant.leases.show', $lease) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
