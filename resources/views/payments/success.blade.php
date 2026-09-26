@extends(auth()->user()->isAdmin() || auth()->user()->isStaff() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Payment Successful')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 text-center">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-5">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <h1 class="h4 mt-3">Payment Successful</h1>
                <p class="text-muted mb-0">Thank you! Your payment is being processed and your records will update shortly.</p>
            </div>
        </div>
    </div>
</div>
@endsection
