<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class XenditWebhookController extends Controller
{
    public function __construct(protected PaymentCompletionService $completion) {}

    public function handle(Request $request): JsonResponse
    {
        $token = $request->header('X-Callback-Token');

        abort_unless($token && hash_equals((string) config('xendit.callback_token'), $token), 401);

        $invoiceId = $request->input('id');
        $status = $request->input('status');
        $paymentId = $request->input('payment_id');

        $payment = Payment::where('xendit_invoice_id', $invoiceId)->first();

        if ($payment && $status === 'PAID') {
            $this->completion->complete($payment, $paymentId);
        }

        return response()->json(['received' => true]);
    }
}
