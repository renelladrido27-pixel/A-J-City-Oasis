@extends('layouts.admin')

@section('title', 'Add Account')

@section('content')
<x-page-header title="Add Account" />

<div class="card border-0 shadow-sm" style="max-width: 560px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <option value="tenant" @selected(old('role') === 'tenant')>Tenant</option>
                    <option value="staff" @selected(old('role') === 'staff')>Staff (Caretaker)</option>
                    <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
