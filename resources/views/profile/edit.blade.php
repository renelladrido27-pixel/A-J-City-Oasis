@extends(auth()->user()->isAdmin() || auth()->user()->isStaff() ? 'layouts.admin' : 'layouts.app')

@section('title', 'My Profile')

@section('content')
<x-page-header title="My Profile"/>

<style>
    .avatar-upload { position: relative; width: 88px; height: 88px; flex-shrink: 0; }
    .avatar-preview {
        position: relative;
        width: 88px; height: 88px;
        border-radius: 50%;
        background: #e4e6ec;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden;
        color: #8b909a;
        font-size: 2rem;
    }
    .avatar-preview img {
        position: absolute; inset: 0;
        width: 100%; height: 100%;
        object-fit: cover;
        display: none;
    }
    .avatar-badge {
        position: absolute; right: -2px; bottom: -2px;
        width: 30px; height: 30px;
        border-radius: 50%;
        background: var(--bs-primary);
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid #fff;
        cursor: pointer;
        font-size: .8rem;
    }
    .avatar-badge:hover { filter: brightness(0.9); }
</style>

<div class="row g-4" style="max-width: 720px;">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="avatar-upload">
                            <div class="avatar-preview" id="avatarPreview">
                                <img id="avatarImg" @if ($user->photoUrl()) src="{{ $user->photoUrl() }}" style="display:block;" @endif alt="{{ $user->name }}">
                                <i class="bi bi-person-fill" id="avatarPlaceholder" style="{{ $user->photoUrl() ? 'display:none;' : '' }}"></i>
                            </div>
                            <label class="avatar-badge" for="avatarInput" title="Change photo">
                                <i class="bi bi-camera-fill"></i>
                            </label>
                            <input type="file" name="photo" id="avatarInput" accept="image/jpeg,image/png,image/webp" class="d-none">
                        </div>
                        <div>
                            <div class="fw-semibold">{{ $user->name }}</div>
                
                            <div class="text-danger small mt-1" id="avatarError">@error('photo'){{ $message }}@enderror</div>
                            <div class="text-muted small">JPG, PNG or WebP &middot; up to 2 MB</div>
                            @if ($user->photo)
                                <button type="submit" form="removePhotoForm" class="btn btn-link btn-sm text-danger p-0 mt-1 d-block">Remove photo</button>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <h2 class="h6 border-top pt-3 mb-3">Change Password</h2>
                    <p class="text-muted small">Leave these blank to keep your current password.</p>

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control @error('new_password') is-invalid @enderror" autocomplete="new-password">
                            @error('new_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="new_password_confirmation" class="form-control" autocomplete="new-password">
                        </div>
                    </div>

                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                </form>

                @if ($user->photo)
                    <form id="removePhotoForm" method="POST" action="{{ route('profile.photo.destroy') }}" class="d-none">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('avatarInput').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        // Same rules as the server (and the site's other upload boxes).
        const problem = !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
            ? 'Only JPG, PNG or WebP photos are allowed.'
            : file.size > 2 * 1024 * 1024 ? 'That photo is larger than 2 MB — please choose a smaller one.' : '';
        document.getElementById('avatarError').textContent = problem;
        if (problem) { e.target.value = ''; return; }
        const img = document.getElementById('avatarImg');
        img.src = URL.createObjectURL(file);
        img.style.display = 'block';
        document.getElementById('avatarPlaceholder').style.display = 'none';
    });
</script>
@endsection
