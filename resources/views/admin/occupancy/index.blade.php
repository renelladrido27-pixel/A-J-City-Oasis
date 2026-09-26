@extends('layouts.admin')

@section('title', 'Occupancy')

@section('content')
<x-page-header title="Occupancy" :subtitle="$totalRooms.' rooms across '.$totalProperties.' properties'" />

<div class="d-flex flex-wrap gap-3 mb-4">
    <span class="d-flex align-items-center gap-2 small"><span class="rounded-circle bg-secondary" style="width: .9rem; height: .9rem;"></span>Vacant</span>
    <span class="d-flex align-items-center gap-2 small"><span class="rounded-circle bg-success" style="width: .9rem; height: .9rem;"></span>Occupied</span>
    <span class="d-flex align-items-center gap-2 small"><span class="rounded-circle bg-danger" style="width: .9rem; height: .9rem;"></span>Occupied — overdue payment</span>
    <span class="d-flex align-items-center gap-2 small"><span class="rounded-circle bg-warning" style="width: .9rem; height: .9rem;"></span>Maintenance</span>
    <span class="d-flex align-items-center gap-2 small"><span class="rounded-circle bg-info" style="width: .9rem; height: .9rem;"></span>Reserved</span>
</div>

@foreach ($properties as $property)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h6 mb-3">{{ $property->name }}</h2>
            @if ($property->rooms->isEmpty())
                <x-empty-state icon="bi-door-closed" message="No rooms for this property yet." />
            @else
                <div class="row row-cols-3 row-cols-sm-4 row-cols-md-6 row-cols-lg-8 g-2">
                    @foreach ($property->rooms as $room)
                        @php
                            $lease = $room->leases->first();
                            $isOverdue = $room->status === 'occupied' && $lease && $lease->payments->isNotEmpty();
                            $tile = match (true) {
                                $isOverdue => ['bg-danger', 'text-white'],
                                $room->status === 'occupied' => ['bg-success', 'text-white'],
                                $room->status === 'maintenance' => ['bg-warning', 'text-dark'],
                                $room->status === 'reserved' => ['bg-info', 'text-white'],
                                default => ['bg-light border', 'text-dark'],
                            };
                        @endphp
                        <div class="col">
                            <button type="button"
                               class="btn d-flex flex-column align-items-center justify-content-center rounded p-2 w-100 {{ $tile[0] }} {{ $tile[1] }}"
                               style="aspect-ratio: 1; min-height: 70px;"
                               data-bs-toggle="modal" data-bs-target="#roomDetailsModal"
                               data-room-number="{{ $room->room_number }}"
                               data-property="{{ $property->name }}"
                               data-floor="{{ $room->floor }}"
                               data-type="{{ ucfirst($room->type) }}"
                               data-rate="{{ number_format($room->monthly_rate, 2) }}"
                               data-status="{{ $isOverdue ? 'Occupied (overdue payment)' : ucfirst($room->status) }}"
                               data-status-color="{{ $tile[0] }}"
                               data-tenant-name="{{ $lease?->tenant->name ?? '' }}"
                               data-tenant-email="{{ $lease?->tenant->email ?? '' }}"
                               data-tenant-phone="{{ $lease?->tenant->phone ?? '' }}"
                               data-lease-start="{{ $lease?->start_date?->format('M d, Y') ?? '' }}"
                               title="Room {{ $room->room_number }} — {{ $isOverdue ? 'Occupied (overdue payment)' : ucfirst($room->status) }}{{ $lease ? ' — '.$lease->tenant->name : '' }}">
                                <span class="fw-semibold small">{{ $room->room_number }}</span>
                                @if ($isOverdue)
                                    <i class="bi bi-exclamation-triangle-fill small"></i>
                                @endif
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endforeach

<div class="row g-3 mb-4">
    <x-stat-card label="Occupied" :value="$summary['occupied']" icon="bi-house-check" color="success" />
    <x-stat-card label="Reserved" :value="$summary['reserved']" icon="bi-bookmark-check" color="info" />
    <x-stat-card label="Available" :value="$summary['vacant']" icon="bi-house" color="secondary" />
    <x-stat-card label="Maintenance" :value="$summary['maintenance']" icon="bi-tools" color="warning" />
</div>

<div class="modal fade" id="roomDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Room <span id="rdRoomNumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-muted">Property</dt>
                    <dd class="col-7" id="rdProperty"></dd>
                    <dt class="col-5 fw-normal text-muted">Floor</dt>
                    <dd class="col-7" id="rdFloor"></dd>
                    <dt class="col-5 fw-normal text-muted">Type</dt>
                    <dd class="col-7" id="rdType"></dd>
                    <dt class="col-5 fw-normal text-muted">Monthly Rate</dt>
                    <dd class="col-7">₱<span id="rdRate"></span></dd>
                    <dt class="col-5 fw-normal text-muted">Status</dt>
                    <dd class="col-7"><span id="rdStatus" class="badge"></span></dd>
                </dl>
                <hr>
                <div id="rdTenantSection">
                    <h6 class="mb-2">Current Tenant</h6>
                    <dl class="row mb-0">
                        <dt class="col-5 fw-normal text-muted">Name</dt>
                        <dd class="col-7" id="rdTenantName"></dd>
                        <dt class="col-5 fw-normal text-muted">Email</dt>
                        <dd class="col-7" id="rdTenantEmail"></dd>
                        <dt class="col-5 fw-normal text-muted">Phone</dt>
                        <dd class="col-7" id="rdTenantPhone"></dd>
                        <dt class="col-5 fw-normal text-muted">Lease Start</dt>
                        <dd class="col-7" id="rdLeaseStart"></dd>
                    </dl>
                </div>
                <p id="rdNoTenant" class="text-muted mb-0 d-none">No tenant currently residing in this room.</p>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('roomDetailsModal').addEventListener('show.bs.modal', function (event) {
        const d = event.relatedTarget.dataset;

        document.getElementById('rdRoomNumber').textContent = d.roomNumber;
        document.getElementById('rdProperty').textContent = d.property;
        document.getElementById('rdFloor').textContent = d.floor;
        document.getElementById('rdType').textContent = d.type;
        document.getElementById('rdRate').textContent = d.rate;

        const statusBadge = document.getElementById('rdStatus');
        statusBadge.textContent = d.status;
        statusBadge.className = 'badge ' + d.statusColor.replace('border', '').trim();

        const hasTenant = !!d.tenantName;
        document.getElementById('rdTenantSection').classList.toggle('d-none', !hasTenant);
        document.getElementById('rdNoTenant').classList.toggle('d-none', hasTenant);

        if (hasTenant) {
            document.getElementById('rdTenantName').textContent = d.tenantName;
            document.getElementById('rdTenantEmail').textContent = d.tenantEmail;
            document.getElementById('rdTenantPhone').textContent = d.tenantPhone || '—';
            document.getElementById('rdLeaseStart').textContent = d.leaseStart;
        }
    });
</script>
@endsection
