<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Api\Concerns\HandlesPaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use HandlesPaymentGateway;

    /**
     * Rent/utility payment history plus the outstanding balance and next due
     * date — same computation as the web app's PaymentController::index().
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user();

        $payments = Payment::whereHas('lease', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->with('lease')
            ->latest('due_date')
            ->get();

        $pending = $payments->where('status', 'pending');
        $outstandingBalance = (float) $pending->sum('amount');
        $nextDue = $pending->sortBy('due_date')->first();

        return response()->json([
            'outstanding_balance' => $outstandingBalance,
            'next_due_date' => $nextDue?->due_date?->toDateString(),
            'next_due_payment_id' => $nextDue?->id,
            'history' => PaymentResource::collection($payments),
        ]);
    }

    public function pay(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizePaymentOwner($request, $payment);
        abort_unless($payment->status === 'pending', 422, 'This payment is not payable.');

        try {
            $result = $this->startOrResumePayment($payment, $request->user()->email);
        } catch (PaymentGatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        if ($result['status'] === 'paid') {
            $result['payment'] = PaymentResource::make($payment->fresh());
        }

        return response()->json($result);
    }

    public function checkStatus(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizePaymentOwner($request, $payment);

        try {
            $result = $this->pollPaymentStatus($payment);
        } catch (PaymentGatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        if ($result['status'] === 'paid') {
            $result['payment'] = PaymentResource::make($payment->fresh());
        }

        return response()->json($result);
    }

    protected function authorizePaymentOwner(Request $request, Payment $payment): void
    {
        $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;

        abort_unless($tenant && $tenant->id === $request->user()->id, 403);
    }
}
