@extends('layouts.admin')

@section('title', 'Users')

@section('content')
<x-page-header title="Users" subtitle="Admin and staff accounts — for tenants, see the Tenants page">
    <x-slot:actions>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Account</a>
    </x-slot:actions>
</x-page-header>

<div class="mb-3">
    <div class="btn-group" role="group" aria-label="Filter by role">
        <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary {{ request('role') ? '' : 'active' }}">All</a>
        @foreach (['admin', 'staff', 'tenant'] as $role)
            <a href="{{ route('admin.users.index', ['role' => $role]) }}" class="btn btn-sm btn-outline-secondary {{ request('role') === $role ? 'active' : '' }}">{{ ucfirst($role) }}</a>
        @endforeach
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="fw-medium">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->phone ?? '—' }}</td>
                        <td><span class="badge text-bg-light">{{ ucfirst($user->role) }}</span></td>
                        <td><x-status-badge :status="$user->is_active ? 'active' : 'deactivated'" /></td>
                        <td class="text-end">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            @unless ($user->id === auth()->id())
                                <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                        <i class="bi {{ $user->is_active ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                                    </button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="bi-people" message="No accounts found." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $users->links() }}</div>
@endsection
