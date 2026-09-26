@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<x-page-header title="Admin Dashboard" subtitle="Overview of A & J OASIS operations" />

<h2 class="h6 text-muted mb-3">Summary cards</h2>
<div class="row g-3 mb-4">
    <x-stat-card label="Tenants" :value="$stats['tenants']" icon="bi-people" color="primary" />
    <x-stat-card label="Occupied" :value="$stats['occupied_rooms']" icon="bi-house-check" color="success" />
    <x-stat-card label="Vacant" :value="$stats['vacant_rooms']" icon="bi-house" color="secondary" />
    <x-stat-card label="Overdue" :value="$stats['overdue_payments']" icon="bi-exclamation-circle" color="danger" />
    <x-stat-card label="Income (mo)" :value="'₱'.number_format($stats['income_this_month'], 2)" icon="bi-cash-stack" color="success" />
    <x-stat-card label="Bookings" :value="$stats['pending_bookings']" icon="bi-calendar-check" color="warning" />
    <x-stat-card label="Transfer Req." :value="$stats['pending_transfers']" icon="bi-arrow-left-right" color="info" />
    <x-stat-card label="Maint. Req." :value="$stats['open_maintenance']" icon="bi-tools" color="warning" />
</div>

<h2 class="h6 text-muted mb-3">Recent activity</h2>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @if ($activity->isEmpty())
            <x-empty-state icon="bi-activity" message="No recent activity yet." />
        @else
            <ul class="list-group list-group-flush">
                @foreach ($activity as $item)
                    <li class="list-group-item d-flex align-items-center gap-3 py-3">
                        <span class="rounded-circle d-flex align-items-center justify-content-center bg-{{ $item['color'] }}-subtle text-{{ $item['color'] }} flex-shrink-0" style="width: 2.25rem; height: 2.25rem;">
                            <i class="bi {{ $item['icon'] }}"></i>
                        </span>
                        <span class="flex-grow-1">{{ $item['text'] }}</span>
                        <span class="text-muted small flex-shrink-0">{{ $item['at']->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
