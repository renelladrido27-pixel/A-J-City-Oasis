@extends(auth()->user()->isAdmin() || auth()->user()->isStaff() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Utility Bills')

@section('content')
<x-page-header :title="auth()->user()->isAdmin() ? 'All Utility Bills' : 'My Utility Bills'">
    @if (auth()->user()->isAdmin())
        <x-slot:actions>
            <a href="{{ route('admin.utility-bills.create-any') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Utility Bill</a>
        </x-slot:actions>
    @endif
</x-page-header>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    @if (auth()->user()->isAdmin())<th>Tenant</th><th>Room</th>@endif
                    <th>Type</th><th>Amount</th><th>Due</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bills as $bill)
                    <tr>
                        @if (auth()->user()->isAdmin())
                            <td>{{ $bill->lease->tenant->name }}</td>
                            <td>Room {{ $bill->lease->room->room_number }}</td>
                        @endif
                        <td>{{ ucfirst($bill->type) }}</td>
                        <td>₱{{ number_format($bill->amount, 2) }}</td>
                        <td>{{ $bill->due_date->format('M d, Y') }}</td>
                        <td><x-status-badge :status="$bill->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="bi-lightning-charge" message="No utility bills yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $bills->links() }}</div>
@endsection
