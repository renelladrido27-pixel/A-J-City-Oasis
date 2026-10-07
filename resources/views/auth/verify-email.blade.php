@extends('layouts.app')

@section('title', 'Verify Your Email')

@section('content')
@include('partials.auth-styles')

<div class="auth-a">
    <div class="card">
        <div class="brandmark">
            <div class="mark"><img src="{{ asset('images/logo-mark.svg') }}" alt=""></div>
            <span>A &amp; J OASIS</span>
        </div>

        <p class="subtitle">We sent a 6-digit code to <strong>{{ $email }}</strong>. Enter it below to verify your account.</p>

        <form method="POST" action="{{ route('verification.verify') }}">
            @csrf
            <div class="field-float {{ $errors->has('code') ? 'is-invalid' : '' }}">
                <input type="text" name="code" id="code" placeholder=" " inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6"
                       style="font-size: 1.5rem; letter-spacing: .5rem; text-align: center;" required autofocus>
                <label for="code">Verification code</label>
            </div>
            @error('code')<p class="field-error">{{ $message }}</p>@enderror

            <button type="submit" class="submit-btn">Verify email</button>
        </form>

        <form method="POST" action="{{ route('verification.resend') }}" class="text-center mt-3">
            @csrf
            <p class="switch-line mb-1">Didn't get it? Check your spam folder, or</p>
            <button type="submit" class="btn btn-link btn-sm p-0" data-loading-text="Sending…">send a new code</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">
            @csrf
            <button type="submit" class="btn btn-link btn-sm p-0 text-muted">Sign out</button>
        </form>
    </div>
</div>
@endsection
