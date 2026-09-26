@extends(auth()->user()->isAdmin() || auth()->user()->isStaff() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Bookings')

@section('content')
<x-page-header :title="auth()->user()->isAdmin() ? 'All Bookings' : 'My Bookings'" />

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    @if (auth()->user()->isAdmin())<th>Tenant</th>@endif
                    <th>Room</th><th>Move-in Deadline</th><th>Move-in Date</th><th>Total</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bookings as $booking)
                    <tr>
                        @if (auth()->user()->isAdmin())<td>{{ $booking->tenant->name }}</td>@endif
                        <td>{{ $booking->room->room_number }} <span class="text-muted">({{ $booking->room->property->name }})</span></td>
                        <td>{{ $booking->move_in_deadline->format('M d, Y') }}</td>
                        <td>{{ $booking->move_in_date?->format('M d, Y') ?? '—' }}</td>
                        <td>₱{{ number_format($booking->total_amount, 2) }}</td>
                        <td><x-status-badge :status="$booking->status" /></td>
                        <td class="text-end">
                            @unless (auth()->user()->isAdmin())
                                <a href="{{ route('tenant.bookings.show', $booking) }}" class="btn btn-sm btn-outline-primary">View</a>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="bi-calendar-check" message="No bookings yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $bookings->links() }}</div>
@endsection
