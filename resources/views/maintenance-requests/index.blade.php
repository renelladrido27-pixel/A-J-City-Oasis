@extends(auth()->user()->isAdmin() || auth()->user()->isStaff() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Maintenance Requests')

@section('content')
<x-page-header title="Maintenance Requests" />

@if (auth()->user()->isTenant())
    <div class="d-flex justify-content-end mb-3">
        @if ($activeLease)
            <a href="{{ route('tenant.maintenance-requests.create', $activeLease) }}" class="btn btn-sm btn-primary"><i class="bi bi-tools me-1"></i>Report Maintenance Issue</a>
        @else
            <button class="btn btn-sm btn-primary" disabled title="You need an active lease to report an issue."><i class="bi bi-tools me-1"></i>Report Maintenance Issue</button>
        @endif
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    @if (! auth()->user()->isTenant())<th>Tenant</th>@endif
                    <th>Room</th><th>Category</th><th>Description</th><th>Photo</th><th>Status</th><th>Assigned To</th><th>Scheduled</th>
                    <th>{{ auth()->user()->isTenant() ? 'Action' : 'Assign' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $request)
                    <tr>
                        @if (! auth()->user()->isTenant())<td>{{ $request->tenant->name }}</td>@endif
                        <td>{{ $request->room->room_number }}</td>
                        <td>{{ $request->category }}</td>
                        <td>{{ $request->description }}</td>
                        <td>
                            @if ($request->photoUrl())
                                <a href="{{ $request->photoUrl() }}" target="_blank">
                                    <img src="{{ $request->photoUrl() }}" alt="Issue photo" class="rounded" style="width: 44px; height: 44px; object-fit: cover;">
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><x-status-badge :status="$request->status" /></td>
                        <td>
                            @if ($request->assignee)
                                <span class="fw-medium"><i class="bi bi-person-gear me-1"></i>{{ $request->assignee->name }}</span>
                                @if ($request->assignee->phone)
                                    <div class="small"><a href="tel:{{ $request->assignee->phone }}" class="text-decoration-none text-muted"><i class="bi bi-telephone me-1"></i>{{ $request->assignee->phone }}</a></div>
                                @endif
                            @else
                                <span class="text-muted">{{ auth()->user()->isTenant() ? 'Not yet assigned' : '—' }}</span>
                            @endif
                        </td>
                        <td>{{ $request->scheduled_date?->format('M d, Y') ?? '—' }}</td>
                        @if (! auth()->user()->isTenant())
                            {{-- Admin/staff only assign. The status follows the assignment, and the tenant closes the request. --}}
                            <td>
                                @if ($request->isOpen())
                                    <form method="POST" action="{{ auth()->user()->isAdmin() ? route('admin.maintenance-requests.update', $request) : route('staff.maintenance.update', $request) }}" class="d-flex flex-wrap gap-1" style="min-width: 300px;">
                                        @csrf @method('PUT')
                                        <select name="assigned_to" class="form-select form-select-sm" style="width: auto;" aria-label="Assign to">
                                            <option value="">Unassigned</option>
                                            @foreach ($assignees as $assignee)
                                                <option value="{{ $assignee->id }}" @selected($request->assigned_to === $assignee->id)>{{ $assignee->name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="date" name="scheduled_date" class="form-control form-control-sm" style="width: auto;" value="{{ $request->scheduled_date?->toDateString() }}" aria-label="Scheduled date">
                                        <button class="btn btn-sm btn-primary" data-loading-text="Saving…">Assign</button>
                                    </form>
                                    @if ($request->status === 'in_progress')
                                        <div class="small text-muted mt-1"><i class="bi bi-hourglass-split me-1"></i>Waiting for the tenant to confirm it's fixed</div>
                                    @endif
                                @else
                                    <span class="text-muted small">
                                        {{ $request->status === 'resolved' ? 'Confirmed by tenant'.($request->resolved_at ? ' · '.$request->resolved_at->format('M d, Y') : '') : 'Cancelled by tenant' }}
                                    </span>
                                @endif
                            </td>
                        @else
                            {{-- The tenant closes their own request. --}}
                            <td>
                                @if ($request->isOpen())
                                    <div class="d-flex flex-wrap gap-1">
                                        <form method="POST" action="{{ route('tenant.maintenance-requests.resolve', $request) }}" onsubmit="return confirm('Confirm that this issue has been fixed? This closes the request.')">
                                            @csrf
                                            <button class="btn btn-sm btn-success" data-loading-text="Saving…"><i class="bi bi-check2-circle me-1"></i>Mark as resolved</button>
                                        </form>
                                        @if ($request->status === 'pending')
                                            <form method="POST" action="{{ route('tenant.maintenance-requests.cancel', $request) }}" onsubmit="return confirm('Cancel this maintenance request?')">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary" data-loading-text="Cancelling…">Cancel</button>
                                            </form>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()->isTenant() ? 8 : 9 }}"><x-empty-state icon="bi-tools" message="No maintenance requests yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $requests->links() }}</div>
@endsection
