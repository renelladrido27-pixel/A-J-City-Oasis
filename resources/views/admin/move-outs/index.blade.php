@extends('layouts.admin')

@section('title', 'Move-Outs')

@section('content')
<x-page-header title="Move-Outs" />

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tenant</th><th>Room</th><th>Move-Out Date</th><th class="text-end">Refund</th><th class="text-end">Balance Due</th><th>Status</th><th class="text-end"></th></tr>
            </thead>
            <tbody>
                @forelse ($moveOuts as $moveOut)
                    <tr>
                        <td class="fw-medium">{{ $moveOut->lease->tenant->name }}</td>
                        <td>{{ $moveOut->lease->room->room_number }} <span class="text-muted small">&middot; {{ $moveOut->lease->room->property->name }}</span></td>
                        <td>{{ ($moveOut->actual_move_out_date ?? $moveOut->requested_move_out_date)->format('M d, Y') }}</td>
                        <td class="text-end">
                            ₱{{ number_format($moveOut->refund_amount, 2) }}
                            @unless ($moveOut->isFinalized())<div class="small text-muted">estimate</div>@endunless
                        </td>
                        <td class="text-end">{{ $moveOut->balance_due > 0 ? '₱'.number_format($moveOut->balance_due, 2) : '—' }}</td>
                        <td><x-status-badge :status="$moveOut->isFinalized() ? $moveOut->refund_status : 'awaiting_inspection'" /></td>
                        <td class="text-end">
                            <a href="{{ route('admin.move-outs.show', $moveOut) }}" class="btn btn-sm {{ $moveOut->isFinalized() ? 'btn-outline-secondary' : 'btn-primary' }}">
                                {{ $moveOut->isFinalized() ? 'View' : 'Inspect & Finalize' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="bi-box-arrow-left" message="No move-out requests yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $moveOuts->links() }}</div>
@endsection
