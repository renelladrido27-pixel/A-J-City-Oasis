@extends('layouts.app')

@section('title', 'Forgot Password')
@section('meta_description', 'Reset your A &amp; J OASIS account password.')

@section('content')
@include('partials.auth-styles')

<div class="auth-a">
    <div class="card">
        <div class="brandmark">
            <div class="mark"><img src="{{ asset('images/logo-mark.svg') }}" alt=""></div>
            <span>A &amp; J OASIS</span>
        </div>

        <p class="subtitle">Enter your account email and we'll send you a link to reset your password.</p>

        @if (session('status'))
            <div class="status-note">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field-float {{ $errors->has('email') ? 'is-invalid' : '' }}">
                <input type="email" name="email" id="email" placeholder=" " value="{{ old('email') }}" required autofocus>
                <label for="email">Email</label>
            </div>
            @error('email')<p class="field-error">{{ $message }}</p>@enderror

            <button type="submit" class="submit-btn">Send Reset Link</button>
            <p class="switch-line"><a href="{{ route('login') }}">Back to Sign in</a></p>
        </form>
    </div>
</div>
@endsection
