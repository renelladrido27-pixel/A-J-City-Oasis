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
                    @php($attempt = (int) request('attempt', 0))
                    @if ($attempt < 10)
                        {{-- Re-polls Xendit automatically (each reload runs success() again). --}}
                        <div class="spinner-border text-success" style="width: 3rem; height: 3rem;" role="status" aria-hidden="true"></div>
                        <h1 class="h4 mt-3">Confirming Your Payment…</h1>
                        <p class="text-muted mb-0">This usually takes a few seconds. This page updates on its own — no need to refresh.</p>
                        <script>
                            setTimeout(function () {
                                const url = new URL(window.location.href);
                                url.searchParams.set('attempt', {{ $attempt + 1 }});
                                window.location.replace(url);
                            }, 3000);
                        </script>
                    @else
                        <i class="bi bi-hourglass-split text-warning" style="font-size: 3rem;"></i>
                        <h1 class="h4 mt-3">Still Waiting for Xendit</h1>
                        <p class="text-muted">If you completed the payment, it will show as paid shortly. You can check again now.</p>
                        <form method="POST" action="{{ route('payments.check-status', $payment) }}">
                            @csrf
                            <button class="btn btn-outline-primary"><i class="bi bi-arrow-clockwise me-1"></i>Check Again</button>
                        </form>
                    @endif
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
