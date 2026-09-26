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
                    <th>Room</th><th>Category</th><th>Description</th><th>Photo</th><th>Status</th>
                    @if (! auth()->user()->isTenant())<th>Scheduled</th><th>Update</th>@endif
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
                        @if (! auth()->user()->isTenant())
                            <td>{{ $request->scheduled_date?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ auth()->user()->isAdmin() ? route('admin.maintenance-requests.update', $request) : route('staff.maintenance.update', $request) }}" class="d-flex flex-wrap gap-1" style="min-width: 340px;">
                                    @csrf @method('PUT')
                                    <select name="status" class="form-select form-select-sm" style="width: auto;">
                                        @foreach (['pending', 'in_progress', 'completed', 'cancelled'] as $status)
                                            <option value="{{ $status }}" @selected($request->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                        @endforeach
                                    </select>
                                    <select name="assigned_to" class="form-select form-select-sm" style="width: auto;">
                                        <option value="">Unassigned</option>
                                        @foreach ($assignees as $assignee)
                                            <option value="{{ $assignee->id }}" @selected($request->assigned_to === $assignee->id)>{{ $assignee->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="date" name="scheduled_date" class="form-control form-control-sm" style="width: auto;" value="{{ $request->scheduled_date?->toDateString() }}">
                                    <button class="btn btn-sm btn-primary">Update</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()->isTenant() ? 5 : 7 }}"><x-empty-state icon="bi-tools" message="No maintenance requests yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $requests->links() }}</div>
@endsection
