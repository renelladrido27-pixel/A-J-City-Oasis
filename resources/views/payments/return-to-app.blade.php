@extends('layouts.app')

@section('title', $failed ? 'Payment Not Completed' : 'Payment Received')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 text-center">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-5">
                @if ($failed)
                    <i class="bi bi-x-circle-fill text-danger" style="font-size: 3rem;"></i>
                    <h1 class="h4 mt-3">Payment Not Completed</h1>
                    <p class="text-muted mb-0">Your payment didn't go through. Return to the A&amp;J CITY OASIS app to try again.</p>
                @else
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                    <h1 class="h4 mt-3">Payment Received</h1>
                    <p class="text-muted mb-0">You can close this page and return to the A&amp;J CITY OASIS app — it will confirm your payment automatically.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
