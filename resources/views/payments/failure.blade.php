@extends(auth()->user()->isAdmin() || auth()->user()->isStaff() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Payment Failed')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 text-center">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-5">
                <i class="bi bi-x-circle-fill text-danger" style="font-size: 3rem;"></i>
                <h1 class="h4 mt-3">Payment Failed</h1>
                <p class="text-muted mb-0">Your payment could not be completed. Please try again.</p>
            </div>
        </div>
    </div>
</div>
@endsection
