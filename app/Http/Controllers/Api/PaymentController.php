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
            // The upfront booking payment is paid through the booking screens, but once
            // paid it belongs in the history here so its receipt can be opened.
            ->orWhere(fn ($q) => $q->where('status', 'paid')->whereHas('booking', fn ($b) => $b->where('user_id', $tenant->id)))
            ->with(['lease', 'booking', 'utilityBill'])
            ->latest('due_date')
            ->get();

        $pending = $payments->whereIn('status', Payment::UNPAID);
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
        abort_unless($payment->isPayable(), 422, 'This payment is not payable.');

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

    /**
     * The receipt for a paid payment — the same details as the website's
     * printable receipt page, for the app to lay out itself.
     */
    public function receipt(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizePaymentOwner($request, $payment);
        abort_unless($payment->status === 'paid', 404, 'There is no receipt for this payment yet.');

        $payment->load(['lease.room.property', 'lease.tenant', 'booking.room.property', 'booking.tenant', 'utilityBill']);
        $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;
        $room = $payment->lease?->room ?? $payment->booking?->room;

        return response()->json(['receipt' => [
            'number' => str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            'business' => 'A&J CITY OASIS',
            'business_address' => 'Koronadal City, South Cotabato',
            'billed_to' => ['name' => $tenant->name, 'email' => $tenant->email],
            'room' => ['number' => $room?->room_number, 'property' => $room?->property?->name],
            'description' => ucfirst(str_replace('_', ' ', $payment->type)),
            // One line per amount: three for the upfront booking payment, else one.
            'lines' => $payment->lines(),
            'amount' => (float) $payment->amount,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            // The bill's due date, or the first rent due for the upfront payment.
            'due_date' => ($due = $payment->receiptDueDate()) ? ['label' => $due['label'], 'date' => $due['date']->toDateString()] : null,
            'refund_note' => $payment->refundNote(),
            'reference' => $payment->xendit_payment_id ?? $payment->xendit_invoice_id ?? 'Recorded manually',
            'bill_photo_url' => $payment->utilityBill?->receiptUrl(),
        ]]);
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
