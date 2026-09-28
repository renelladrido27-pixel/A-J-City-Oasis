@extends(auth()->user()->isAdmin() || auth()->user()->isStaff() ? 'layouts.admin' : 'layouts.app')

@section('title', 'Payment Successful')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 text-center">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-5">
                @if ($payment && $payment->status === 'paid')
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                    <h1 class="h4 mt-3">Payment Successful</h1>
                    <p class="text-muted">Your {{ str_replace('_', ' ', $payment->type) }} payment of ₱{{ number_format($payment->amount, 2) }} has been confirmed.</p>
                    <a href="{{ route('payments.receipt', $payment) }}" class="btn btn-primary"><i class="bi bi-receipt me-1"></i>View Receipt</a>
                @elseif ($payment)
                    <i class="bi bi-hourglass-split text-warning" style="font-size: 3rem;"></i>
                    <h1 class="h4 mt-3">Payment Processing</h1>
                    <p class="text-muted">Xendit hasn't confirmed this payment yet. This usually takes a few seconds.</p>
                    <form method="POST" action="{{ route('payments.check-status', $payment) }}">
                        @csrf
                        <button class="btn btn-outline-primary"><i class="bi bi-arrow-clockwise me-1"></i>Check Again</button>
                    </form>
                @else
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                    <h1 class="h4 mt-3">Payment Successful</h1>
                    <p class="text-muted mb-0">Thank you! Your payment is being processed and your records will update shortly.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
