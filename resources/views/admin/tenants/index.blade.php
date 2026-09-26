@extends('layouts.admin')

@section('title', 'Tenants')

@section('content')
@php
    $sortLink = function ($column, $label) use ($sort, $dir, $search) {
        $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
        $icon = $sort === $column ? ($dir === 'asc' ? 'bi-sort-alpha-down' : 'bi-sort-alpha-up') : 'bi-arrow-down-up text-muted';
        $url = route('admin.tenants.index', array_filter(['search' => $search, 'sort' => $column, 'dir' => $nextDir]));

        return "<a href=\"{$url}\" class=\"text-dark text-decoration-none d-flex align-items-center gap-1\">{$label} <i class=\"bi {$icon} small\"></i></a>";
    };
@endphp
<x-page-header title="Tenants" subtitle="All tenant accounts and their current room">
    <x-slot:actions>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Tenant</a>
    </x-slot:actions>
</x-page-header>

<form method="GET" action="{{ route('admin.tenants.index') }}" class="mb-3">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="dir" value="{{ $dir }}">
    <div class="input-group" style="max-width: 360px;">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input type="text" name="search" class="form-control" placeholder="Search name or email…" value="{{ $search }}">
        @if ($search)
            <a href="{{ route('admin.tenants.index', ['sort' => $sort, 'dir' => $dir]) }}" class="btn btn-outline-secondary">Clear</a>
        @endif
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>{!! $sortLink('name', 'Name') !!}</th>
                    <th>{!! $sortLink('email', 'Email') !!}</th>
                    <th>Phone</th><th>Current Room</th><th>Account Status</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    @php($lease = $tenant->leases->first())
                    <tr>
                        <td class="fw-medium">
                            <a href="{{ route('admin.tenants.show', $tenant) }}" class="text-decoration-none">{{ $tenant->name }}</a>
                        </td>
                        <td>{{ $tenant->email }}</td>
                        <td>{{ $tenant->phone ?? '—' }}</td>
                        <td>
                            @if ($lease)
                                Room {{ $lease->room->room_number }}
                            @else
                                <span class="text-muted">No active lease</span>
                            @endif
                        </td>
                        <td><x-status-badge :status="$tenant->is_active ? 'active' : 'deactivated'" /></td>
                        <td class="text-end">
                            <a href="{{ route('admin.tenants.show', $tenant) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('admin.users.edit', $tenant) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.users.toggle-active', $tenant) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm {{ $tenant->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                    <i class="bi {{ $tenant->is_active ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="bi-people" message="No tenants found." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $tenants->links() }}</div>
@endsection
