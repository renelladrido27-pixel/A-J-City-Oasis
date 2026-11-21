{{-- The reason is required: it is sent to the tenant with the rejection. --}}
<form method="POST" action="{{ route('admin.room-transfers.reject', $transfer) }}" class="d-inline-flex gap-1 align-items-center mt-1">
    @csrf
    <input type="text" name="rejection_reason" class="form-control form-control-sm" style="width: 12rem;" maxlength="500" required placeholder="Reason for rejecting" aria-label="Reason for rejecting">
    <button class="btn btn-sm btn-danger text-nowrap"><i class="bi bi-x-lg me-1"></i>Reject</button>
</form>
