@extends('layouts.app')

@section('title', $activeTab === 'signup' ? 'Register' : 'Login')
@section('meta_description', 'Log in or create a tenant account at A&amp;J CITY OASIS, Koronadal City.')

@php
    // A field-level error only ever belongs to one form (e.g. "name" only exists on
    // sign up), so use that to restore the correct tab after a failed submission —
    // regardless of which URL the form actually posted to.
    $activeTab = $errors->has('name') || $errors->has('password_confirmation') ? 'signup' : old('intent', $activeTab);
@endphp

@section('content')
@include('partials.auth-styles')

<div class="auth-a" id="authA" data-start-tab="{{ $activeTab }}">
    <div class="card">
        <div class="brandmark">
            <div class="mark"><img src="{{ asset('images/logo-mark.svg') }}" alt=""></div>
            <span>A&amp;J CITY OASIS</span>
        </div>

        <div class="tabs">
            <button type="button" class="tab" data-tab="signin">Sign in</button>
            <button type="button" class="tab" data-tab="signup">Create account</button>
        </div>

        {{-- Sign in --}}
        <form class="auth-form" id="signinForm" method="POST" action="{{ route('login') }}">
            @csrf
            <input type="hidden" name="intent" value="signin">

            <div class="field-float {{ $errors->has('email') && $activeTab === 'signin' ? 'is-invalid' : '' }}">
                <input type="email" name="email" id="signin-email" placeholder=" " value="{{ old('email') }}" required autofocus>
                <label for="signin-email">Email</label>
            </div>
            @if ($activeTab === 'signin')
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            @endif

            <div class="field-float pw-wrap">
                <input type="password" name="password" id="signin-password" placeholder=" " required>
                <label for="signin-password">Password</label>
                <button type="button" class="pw-toggle" data-pw-toggle><i class="bi bi-eye"></i></button>
            </div>

            <div class="row-options">
                <label class="checkbox"><input type="checkbox" name="remember"> Remember me</label>
                <a href="{{ route('password.request') }}" class="link-muted">Forgot password?</a>
            </div>

            <button type="submit" class="submit-btn">Sign in</button>
            <p class="switch-line">Don't have an account? <a href="#" data-switch-to="signup">Sign up</a></p>
        </form>

        {{-- Create account --}}
        <form class="auth-form d-none" id="signupForm" method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="intent" value="signup">

            <div class="avatar-upload">
                <div class="avatar-preview">
                    <img id="avatarImg" alt="Profile photo">
                    <i class="bi bi-person-fill" id="avatarPlaceholder"></i>
                </div>
                <label class="avatar-badge" for="avatarInput" title="Add a photo">
                    <i class="bi bi-camera-fill"></i>
                </label>
                <input type="file" name="photo" id="avatarInput" accept="image/jpeg,image/png,image/webp" class="d-none">
            </div>
            <p class="field-error text-center" id="avatarError" style="margin-top: -.75rem;">@error('photo'){{ $message }}@enderror</p>

            <div class="field-float {{ $errors->has('first_name') ? 'is-invalid' : '' }}">
                <input type="text" name="first_name" id="signup-first-name" placeholder=" " value="{{ old('first_name') }}" autocomplete="given-name" required>
                <label for="signup-first-name">First name</label>
            </div>
            @error('first_name')<p class="field-error">{{ $message }}</p>@enderror

            <div class="field-float {{ $errors->has('middle_name') ? 'is-invalid' : '' }}">
                <input type="text" name="middle_name" id="signup-middle-name" placeholder=" " value="{{ old('middle_name') }}" autocomplete="additional-name">
                <label for="signup-middle-name">Middle name (optional)</label>
            </div>
            @error('middle_name')<p class="field-error">{{ $message }}</p>@enderror

            <div class="field-float {{ $errors->has('last_name') ? 'is-invalid' : '' }}">
                <input type="text" name="last_name" id="signup-last-name" placeholder=" " value="{{ old('last_name') }}" autocomplete="family-name" required>
                <label for="signup-last-name">Surname</label>
            </div>
            @error('last_name')<p class="field-error">{{ $message }}</p>@enderror

            <div class="field-float {{ $errors->has('email') && $activeTab === 'signup' ? 'is-invalid' : '' }}">
                <input type="email" name="email" id="signup-email" placeholder=" " value="{{ old('email') }}" required>
                <label for="signup-email">Email</label>
            </div>
            @if ($activeTab === 'signup')
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            @endif

            <div class="field-float {{ $errors->has('phone') ? 'is-invalid' : '' }}">
                <input type="tel" name="phone" id="signup-phone" placeholder=" " value="{{ old('phone') }}" inputmode="tel" autocomplete="tel"
                       pattern="(09|\+639)[0-9]{9}" maxlength="13" title="Philippine mobile number, e.g. 09171234567" required>
                <label for="signup-phone">Mobile number (09XXXXXXXXX)</label>
            </div>
            @error('phone')<p class="field-error">{{ $message }}</p>@enderror

            <div class="field-float pw-wrap {{ $errors->has('password') ? 'is-invalid' : '' }}">
                <input type="password" name="password" id="signup-password" placeholder=" " required>
                <label for="signup-password">Password</label>
                <button type="button" class="pw-toggle" data-pw-toggle><i class="bi bi-eye"></i></button>
            </div>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
            @include('partials.password-rules', ['for' => 'signup-password'])

            <div class="field-float pw-wrap">
                <input type="password" name="password_confirmation" id="signup-password-confirmation" placeholder=" " required>
                <label for="signup-password-confirmation">Confirm password</label>
                <button type="button" class="pw-toggle" data-pw-toggle><i class="bi bi-eye"></i></button>
            </div>

            <button type="submit" class="submit-btn">Create account</button>
            <p class="switch-line">Already have an account? <a href="#" data-switch-to="signin">Sign in</a></p>
        </form>
    </div>
</div>

<script>
    function initAuthForm(rootId) {
        const root = document.getElementById(rootId);
        const tabs = root.querySelectorAll('.tab');
        const forms = { signin: root.querySelector('#signinForm'), signup: root.querySelector('#signupForm') };

        function setTab(tab) {
            tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
            Object.entries(forms).forEach(([key, form]) => form.classList.toggle('d-none', key !== tab));
        }

        tabs.forEach(t => t.addEventListener('click', () => setTab(t.dataset.tab)));
        root.querySelectorAll('[data-switch-to]').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                setTab(link.dataset.switchTo);
            });
        });
        root.querySelectorAll('[data-pw-toggle]').forEach(btn => {
            btn.addEventListener('click', function () {
                const input = btn.parentElement.querySelector('input');
                const icon = btn.querySelector('i');
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                icon.classList.toggle('bi-eye', showing);
                icon.classList.toggle('bi-eye-slash', !showing);
            });
        });

        const avatarInput = root.querySelector('#avatarInput');
        avatarInput?.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;
            // Same rules as the server (and the site's other upload boxes).
            const problem = !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
                ? 'Only JPG, PNG or WebP photos are allowed.'
                : file.size > 2 * 1024 * 1024 ? 'That photo is larger than 2 MB — please choose a smaller one.' : '';
            root.querySelector('#avatarError').textContent = problem;
            if (problem) { e.target.value = ''; return; }
            const img = root.querySelector('#avatarImg');
            img.src = URL.createObjectURL(file);
            img.style.display = 'block';
            root.querySelector('#avatarPlaceholder').style.display = 'none';
        });

        setTab(root.dataset.startTab || 'signin');
    }

    initAuthForm('authA');
</script>
@endsection
