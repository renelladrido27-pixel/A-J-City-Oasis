@extends('layouts.app')

@section('title', 'Reset Password')
@section('meta_description', 'Set a new password for your A &amp; J CITY OASIS account.')

@section('content')
@include('partials.auth-styles')

<div class="auth-a">
    <div class="card">
        <div class="brandmark">
            <div class="mark"><img src="{{ asset('images/logo-mark.svg') }}" alt=""></div>
            <span>A &amp; J CITY OASIS</span>
        </div>

        <p class="subtitle">Choose a new password for your account.</p>

        <form method="POST" action="{{ route('password.update') }}" id="resetForm">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="field-float {{ $errors->has('email') ? 'is-invalid' : '' }}">
                <input type="email" name="email" id="email" placeholder=" " value="{{ old('email', $email) }}" required autofocus>
                <label for="email">Email</label>
            </div>
            @error('email')<p class="field-error">{{ $message }}</p>@enderror

            <div class="field-float pw-wrap {{ $errors->has('password') ? 'is-invalid' : '' }}">
                <input type="password" name="password" id="password" placeholder=" " required>
                <label for="password">New Password</label>
                <button type="button" class="pw-toggle" data-pw-toggle><i class="bi bi-eye"></i></button>
            </div>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
            @include('partials.password-rules', ['for' => 'password'])

            <div class="field-float pw-wrap">
                <input type="password" name="password_confirmation" id="password_confirmation" placeholder=" " required>
                <label for="password_confirmation">Confirm New Password</label>
                <button type="button" class="pw-toggle" data-pw-toggle><i class="bi bi-eye"></i></button>
            </div>

            <button type="submit" class="submit-btn">Reset Password</button>
            <p class="switch-line"><a href="{{ route('login') }}">Back to Sign in</a></p>
        </form>
    </div>
</div>

<script>
    document.querySelectorAll('#resetForm [data-pw-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = btn.parentElement.querySelector('input');
            const icon = btn.querySelector('i');
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            icon.classList.toggle('bi-eye', showing);
            icon.classList.toggle('bi-eye-slash', !showing);
        });
    });
</script>
@endsection
