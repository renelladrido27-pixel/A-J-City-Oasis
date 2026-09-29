@extends('layouts.admin')

@section('title', 'Move-Out — Room '.$moveOut->lease->room->room_number)

@section('content')
@php($lease = $moveOut->lease)
<x-page-header :title="'Move-Out — Room '.$lease->room->room_number">
    <x-slot:actions>
        <a href="{{ route('admin.move-outs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>All Move-Outs</a>
    </x-slot:actions>
</x-page-header>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h6 text-muted text-uppercase mb-3">Tenant</h2>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">Name</dt>
                    <dd class="col-7"><a href="{{ route('admin.tenants.show', $lease->tenant) }}" class="text-decoration-none">{{ $lease->tenant->name }}</a></dd>
                    <dt class="col-5 text-muted fw-normal">Room</dt>
                    <dd class="col-7">{{ $lease->room->room_number }} &middot; {{ $lease->room->property->name }}</dd>
                    <dt class="col-5 text-muted fw-normal">Requested date</dt>
                    <dd class="col-7">{{ $moveOut->requested_move_out_date->format('M d, Y') }}</dd>
                    @if ($moveOut->actual_move_out_date)
                        <dt class="col-5 text-muted fw-normal">Finalized</dt>
                        <dd class="col-7">{{ $moveOut->actual_move_out_date->format('M d, Y') }}</dd>
                    @endif
                    @if ($moveOut->reason)
                        <dt class="col-5 text-muted fw-normal">Reason</dt>
                        <dd class="col-7">{{ $moveOut->reason }}</dd>
                    @endif
                    <dt class="col-5 text-muted fw-normal">Status</dt>
                    <dd class="col-7 mb-0"><x-status-badge :status="$moveOut->isFinalized() ? $moveOut->refund_status : 'awaiting_inspection'" /></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        @if ($moveOut->isFinalized())
            {{-- Final settlement --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Settlement</h2>
                    <table class="table mb-0">
                        <tbody>
                            <tr><td>Security deposit</td><td class="text-end">₱{{ number_format($moveOut->security_deposit, 2) }}</td></tr>
                            <tr><td>Unused rent this month</td><td class="text-end">₱{{ number_format($moveOut->unused_rent_credit, 2) }}</td></tr>
                            <tr><td>Less: unpaid bills</td><td class="text-end text-danger">−₱{{ number_format($moveOut->unpaid_dues, 2) }}</td></tr>
                            @foreach ($moveOut->deductions as $deduction)
                                <tr><td>Less: {{ $deduction->description }}</td><td class="text-end text-danger">−₱{{ number_format($deduction->amount, 2) }}</td></tr>
                            @endforeach
                            @if ($moveOut->refund_status === 'balance_due' || $moveOut->refund_status === 'balance_paid')
                                <tr class="fw-semibold"><td>Balance owed by tenant</td><td class="text-end">₱{{ number_format($moveOut->balance_due, 2) }}</td></tr>
                            @else
                                <tr class="fw-semibold"><td>Refund to tenant</td><td class="text-end">₱{{ number_format($moveOut->refund_amount, 2) }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                    @if ($moveOut->balancePayment)
                        <p class="small text-muted mt-3 mb-0">
                            Balance bill: <x-status-badge :status="$moveOut->balancePayment->status" />
                            due {{ $moveOut->balancePayment->due_date->format('M d, Y') }} — the tenant pays it via Xendit from their Payments page.
                        </p>
                    @elseif ($moveOut->refund_amount > 0)
                        <p class="small text-muted mt-3 mb-0">Release the refund to the tenant manually (cash or bank transfer).</p>
                    @endif
                </div>
            </div>
        @else
            {{-- Inspection form --}}
            <form method="POST" action="{{ route('admin.move-outs.finalize', $moveOut) }}" class="card border-0 shadow-sm" id="settlementForm"
                  onsubmit="return confirm('Finalize this move-out? The lease will end, the room becomes vacant, and the tenant is notified. This cannot be undone.')">
                @csrf
                <div class="card-body p-4">
                    <h2 class="h5 mb-1">Room Inspection</h2>
                    <p class="text-muted small">List any damages found. They are deducted from the security deposit; if they exceed it, the tenant gets a balance bill payable via Xendit.</p>

                    <div id="deductionRows">
                        @foreach (old('deductions', [['description' => '', 'amount' => '']]) as $i => $row)
                            <div class="row g-2 mb-2 deduction-row">
                                <div class="col-7"><input type="text" name="deductions[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" class="form-control" placeholder="e.g. Broken window" maxlength="255"></div>
                                <div class="col-4"><input type="number" name="deductions[{{ $i }}][amount]" value="{{ $row['amount'] ?? '' }}" class="form-control deduction-amount" placeholder="₱ amount" step="0.01" min="0.01"></div>
                                <div class="col-1 d-flex"><button type="button" class="btn btn-outline-danger btn-sm remove-row" aria-label="Remove"><i class="bi bi-x"></i></button></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="addRow"><i class="bi bi-plus-lg me-1"></i>Add deduction</button>

                    <table class="table mt-4 mb-0">
                        <tbody>
                            <tr><td>Security deposit</td><td class="text-end">₱{{ number_format($estimate['security_deposit'], 2) }}</td></tr>
                            <tr><td>Unused rent this month <span class="text-muted small">(as of today)</span></td><td class="text-end">₱{{ number_format($estimate['unused_rent_credit'], 2) }}</td></tr>
                            <tr><td>Less: unpaid bills</td><td class="text-end text-danger">−₱{{ number_format($estimate['unpaid_dues'], 2) }}</td></tr>
                            <tr><td>Less: damages</td><td class="text-end text-danger">−₱<span id="damageTotal">0.00</span></td></tr>
                            <tr class="fw-semibold"><td id="resultLabel">Refund to tenant</td><td class="text-end">₱<span id="resultAmount">0.00</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-0 px-4 pb-4">
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Finalize Move-Out</button>
                </div>
            </form>
        @endif
    </div>
</div>

@unless ($moveOut->isFinalized())
<script>
    (function () {
        const credit = {{ $estimate['security_deposit'] + $estimate['unused_rent_credit'] - $estimate['unpaid_dues'] }};
        const rows = document.getElementById('deductionRows');
        const peso = n => n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        let next = rows.children.length;

        function recalc() {
            let damages = 0;
            rows.querySelectorAll('.deduction-amount').forEach(i => damages += parseFloat(i.value) || 0);
            const net = credit - damages;
            document.getElementById('damageTotal').textContent = peso(damages);
            document.getElementById('resultLabel').textContent = net < 0 ? 'Balance tenant must pay (Xendit bill)' : 'Refund to tenant';
            document.getElementById('resultAmount').textContent = peso(Math.abs(net));
        }

        document.getElementById('addRow').addEventListener('click', function () {
            const row = rows.firstElementChild.cloneNode(true);
            row.querySelectorAll('input').forEach(function (input) {
                input.value = '';
                input.name = input.name.replace(/deductions\[\d+\]/, 'deductions[' + next + ']');
            });
            next++;
            rows.appendChild(row);
        });

        rows.addEventListener('click', function (e) {
            const btn = e.target.closest('.remove-row');
            if (!btn) return;
            if (rows.children.length > 1) {
                btn.closest('.deduction-row').remove();
            } else {
                btn.closest('.deduction-row').querySelectorAll('input').forEach(i => i.value = '');
            }
            recalc();
        });

        rows.addEventListener('input', recalc);
        recalc();
    })();
</script>
@endunless
@endsection
