@extends('layouts.admin')

@section('title', 'Edit Account')

@section('content')
<x-page-header :title="'Edit Account — ' . $user->name" />

<div class="card border-0 shadow-sm" style="max-width: 560px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required @disabled($user->id === auth()->id())>
                    @foreach (['tenant', 'staff', 'admin'] as $role)
                        <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $role === 'staff' ? 'Staff (Caretaker)' : ucfirst($role) }}</option>
                    @endforeach
                </select>
                @if ($user->id === auth()->id())
                    <input type="hidden" name="role" value="{{ $user->role }}">
                    <div class="form-text"><i class="bi bi-info-circle me-1"></i>You cannot change your own role.</div>
                @endif
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
