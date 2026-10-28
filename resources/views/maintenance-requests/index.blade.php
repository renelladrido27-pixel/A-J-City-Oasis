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
                            @if ($request->isAssigned())
                                <span class="fw-medium"><i class="bi bi-person-gear me-1"></i>{{ $request->assignee_name }}</span>
                                @if ($request->assignee_phone)
                                    <div class="small"><a href="tel:{{ $request->assignee_phone }}" class="text-decoration-none text-muted"><i class="bi bi-telephone me-1"></i>{{ $request->assignee_phone }}</a></div>
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
                                    {{-- Type who will do the work: they don't need an account. Clear the name to unassign. --}}
                                    <form method="POST" action="{{ auth()->user()->isAdmin() ? route('admin.maintenance-requests.update', $request) : route('staff.maintenance.update', $request) }}" class="d-flex flex-wrap gap-1 assign-form" style="min-width: 300px;">
                                        @csrf @method('PUT')
                                        <input type="text" name="assignee_name" list="assignee-names" class="form-control form-control-sm assignee-name" style="width: 11rem;" maxlength="100"
                                               value="{{ $request->assignee_name }}" placeholder="Staff name" aria-label="Staff name" autocomplete="off">
                                        <input type="tel" name="assignee_phone" class="form-control form-control-sm assignee-phone" style="width: 9rem;" maxlength="20"
                                               value="{{ $request->assignee_phone }}" placeholder="Phone (optional)" aria-label="Staff phone (optional)">
                                        <input type="date" name="scheduled_date" class="form-control form-control-sm" style="width: auto;" value="{{ $request->scheduled_date?->toDateString() }}" aria-label="Visit date">
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
                            {{-- The tenant closes their own request — or deletes it if they changed their mind. --}}
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    {{-- Mark as done unlocks once someone is assigned. --}}
                                    @if ($request->canBeMarkedDone())
                                        <form method="POST" action="{{ route('tenant.maintenance-requests.resolve', $request) }}" onsubmit="return confirm('Confirm that this issue has been fixed? This closes the request.')">
                                            @csrf
                                            <button class="btn btn-sm btn-success" data-loading-text="Saving…"><i class="bi bi-check2-circle me-1"></i>Mark as done</button>
                                        </form>
                                    @elseif ($request->isOpen())
                                        {{-- Visible but not clickable until someone is assigned. --}}
                                        <button type="button" class="btn btn-sm btn-success" disabled
                                                title="Opens once someone is assigned">
                                            <i class="bi bi-check2-circle me-1"></i>Mark as done
                                        </button>
                                    @endif
                                    @if ($request->isDeletable())
                                        <form method="POST" action="{{ route('tenant.maintenance-requests.destroy', $request) }}" onsubmit="return confirm('Remove this maintenance request? {{ $request->assigned_to ? 'The person assigned will be told not to come. ' : '' }}This cannot be undone.')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" data-loading-text="Removing…"><i class="bi bi-trash me-1"></i>Remove</button>
                                        </form>
                                    @endif
                                    @if ($request->isOpen() && ! $request->canBeMarkedDone())
                                        <div class="w-100 small text-muted">
                                            <i class="bi bi-hourglass-split me-1"></i>Waiting to be assigned
                                        </div>
                                    @endif
                                    @unless ($request->isOpen() || $request->isDeletable())
                                        <span class="text-muted">—</span>
                                    @endunless
                                </div>
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
@if (! auth()->user()->isTenant())
    <datalist id="assignee-names">
        @foreach ($assignees as $assignee)
            <option value="{{ $assignee['name'] }}">{{ $assignee['phone'] }}</option>
        @endforeach
    </datalist>
    <script>
        // Picking a name used before fills in that person's phone, if it's empty.
        (function () {
            const phones = @json($assignees->mapWithKeys(fn ($a) => [mb_strtolower($a['name']) => $a['phone']]));
            document.querySelectorAll('.assign-form .assignee-name').forEach(function (input) {
                input.addEventListener('change', function () {
                    const phone = input.form.querySelector('.assignee-phone');
                    const known = phones[input.value.trim().toLowerCase()];
                    if (known && !phone.value) phone.value = known;
                });
            });
        })();
    </script>
@endif
@endsection
