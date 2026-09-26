@extends(auth()->user()->isAdmin() || auth()->user()->isStaff() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Leases')

@section('content')
<x-page-header :title="auth()->user()->isAdmin() ? 'All Leases' : 'My Lease'" />

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    @if (auth()->user()->isAdmin())<th>Tenant</th>@endif
                    <th>Room</th><th>Start Date</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leases as $lease)
                    <tr>
                        @if (auth()->user()->isAdmin())<td>{{ $lease->tenant->name }}</td>@endif
                        <td>{{ $lease->room->room_number }} <span class="text-muted">({{ $lease->room->property->name }})</span></td>
                        <td>{{ $lease->start_date->format('M d, Y') }}</td>
                        <td><x-status-badge :status="$lease->status" /></td>
                        <td class="text-end">
                            <a href="{{ auth()->user()->isAdmin() ? route('admin.leases.show', $lease) : route('tenant.leases.show', $lease) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="bi-file-earmark-text" message="No leases yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $leases->links() }}</div>
@endsection
