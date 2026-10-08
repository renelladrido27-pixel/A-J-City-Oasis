<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentGatewayException;
use App\Models\Lease;
use App\Models\Payment;
use App\Services\NotificationService;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentCompletionService;
use App\Services\RentBillingService;
use App\Services\XenditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        protected XenditService $xendit,
        protected PaymentCompletionService $completion,
        protected NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $payments = $request->user()->isAdmin()
            ? Payment::with(['lease.tenant', 'booking.tenant', 'utilityBill'])->latest()->paginate(25)
            : Payment::whereHas('lease', fn ($q) => $q->where('tenant_id', $request->user()->id))
                ->orWhereHas('booking', fn ($q) => $q->where('user_id', $request->user()->id))
                ->with(['lease', 'booking', 'utilityBill'])
                ->latest()
                ->paginate(25);

        $outstandingBalance = null;
        $nextDueDate = null;

        if ($request->user()->isTenant()) {
            $pending = Payment::whereIn('status', Payment::UNPAID)->where(
                fn ($q) => $q->whereHas('lease', fn ($q2) => $q2->where('tenant_id', $request->user()->id))
                    ->orWhereHas('booking', fn ($q2) => $q2->where('user_id', $request->user()->id))
            );

            $outstandingBalance = (clone $pending)->sum('amount');
            $nextDueDateRaw = (clone $pending)->orderBy('due_date')->value('due_date');
            $nextDueDate = $nextDueDateRaw ? \Carbon\Carbon::parse($nextDueDateRaw) : null;

            $this->notifications->markTypesRead($request->user(), ['payment', 'utility']);
        } elseif ($request->user()->isAdmin()) {
            $this->notifications->markTypesRead($request->user(), ['payment']);
        }

        return view('payments.index', compact('payments', 'outstandingBalance', 'nextDueDate'));
    }

    /**
     * Admin manually generates the next rent payment for an active lease
     * (the daily app:generate-rent-bills job normally does this already).
     */
    public function generateRent(Lease $lease): RedirectResponse
    {
        abort_unless($lease->status === 'active', 422, 'Lease is not active.');

        $billing = app(RentBillingService::class);
        $dueDate = $billing->nextDueDate();

        if (! $billing->billNextMonth($lease)) {
            return back()->withErrors(['rent' => 'Rent for '.$dueDate->format('F Y').' was already generated for this lease.']);
        }

        return back()->with('status', 'Rent payment generated for '.$dueDate->format('F Y').'.');
    }

    /**
     * Admin records a payment collected outside the system (walk-in cash,
     * bank transfer) — applies the same completion side effects (lease
     * activation, receipts, notifications) as a real Xendit payment, just
     * without a gateway reference.
     */
    public function recordManual(Payment $payment): RedirectResponse
    {
        abort_unless($payment->isPayable(), 422, 'This payment is not payable.');

        $this->completion->complete($payment);

        return back()->with('status', 'Payment recorded as paid.');
    }

    /**
     * Tenant initiates payment for a pending payment record via Xendit.
     */
    public function pay(Payment $payment): RedirectResponse
    {
        $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;

        abort_unless($tenant && $tenant->id === auth()->id(), 403);
        abort_unless($payment->isPayable(), 422, 'This payment is not payable.');

        try {
            return redirect()->away(app(PaymentCheckoutService::class)->checkoutUrl($payment, $tenant->email));
        } catch (PaymentGatewayException $e) {
            return back()->withErrors(['gateway' => $e->getMessage()]);
        }
    }

    /**
     * Manually polls Xendit for this invoice's current status and applies completion
     * if it's PAID. Xendit's webhook can only reach a publicly-hosted app, so while
     * developing against a local server this is the only way payment completion can
     * reach the app — it does the same job the webhook does, just pulled instead of pushed.
     */
    public function checkStatus(Payment $payment): RedirectResponse
    {
        $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;

        abort_unless($tenant && $tenant->id === auth()->id(), 403);

        if ($payment->status === 'paid') {
            return back()->with('status', 'This payment is already marked as paid.');
        }

        $invoiceId = $this->xendit->resumableInvoiceId($payment->xendit_invoice_id);

        if (! $invoiceId) {
            return back()->withErrors(['gateway' => 'No Xendit checkout has been started for this payment yet — press Pay via Xendit first.']);
        }

        try {
            $invoice = $this->xendit->getInvoice($invoiceId);
        } catch (PaymentGatewayException $e) {
            return back()->withErrors(['gateway' => $e->getMessage()]);
        }

        if ($invoice['status'] === 'PAID' || $invoice['status'] === 'SETTLED') {
            $this->completion->complete($payment, $invoice['invoice_id']);

            return redirect()->route('payments.success');
        }

        return back()->with('status', 'Not paid yet (status: '.$invoice['status'].'). Complete checkout on Xendit, then check again.');
    }

    /**
     * TEMPORARY test-run route: only reachable when XENDIT_FAKE_MODE is on.
     * Simulates the webhook's "PAID" callback so the flow can be exercised
     * end-to-end without a working Xendit account.
     */
    public function fakeComplete(string $reference): RedirectResponse
    {
        abort_unless(config('xendit.fake_mode'), 404);

        $payment = Payment::where('xendit_invoice_id', $reference)->firstOrFail();
        $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;

        abort_unless($tenant && $tenant->id === auth()->id(), 403);

        $this->completion->complete($payment, 'fake_'.now()->timestamp);

        return redirect()->route('payments.success');
    }

    public function receipt(Payment $payment): View
    {
        $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;

        abort_unless(auth()->user()->isAdmin() || ($tenant && $tenant->id === auth()->id()), 403);
        abort_unless($payment->status === 'paid', 404);

        $payment->load(['lease.room.property', 'lease.tenant', 'booking.room.property', 'booking.tenant']);

        return view('payments.receipt', compact('payment'));
    }

    /**
     * Xendit's success redirect lands here with ?payment={id}. The webhook can't
     * reach a locally-served app, so confirm the invoice with Xendit directly
     * rather than making the tenant go back and press "check status".
     */
    public function success(Request $request): View
    {
        $payment = $request->filled('payment') ? Payment::find($request->integer('payment')) : null;
        $tenant = $payment?->lease?->tenant ?? $payment?->booking?->tenant;

        if (! $payment || ! $tenant || $tenant->id !== auth()->id()) {
            return view('payments.success', ['payment' => null]);
        }

        $invoiceId = $this->xendit->resumableInvoiceId($payment->xendit_invoice_id);

        if ($payment->isPayable() && $invoiceId) {
            try {
                $invoice = $this->xendit->getInvoice($invoiceId);

                if (in_array($invoice['status'], ['PAID', 'SETTLED'], true)) {
                    $this->completion->complete($payment, $invoice['invoice_id']);
                }
            } catch (PaymentGatewayException) {
                // Leave it pending — the webhook or a manual status check will finish it.
            }
        }

        return view('payments.success', ['payment' => $payment->fresh()]);
    }

    /**
     * Public landing page for checkouts started from the mobile app. The phone's
     * browser has no web session, so this can't be behind auth — it just tells
     * the tenant to switch back to the app, which confirms the status itself.
     */
    public function returnToApp(Request $request): View
    {
        return view('payments.return-to-app', ['failed' => $request->boolean('failed')]);
    }

    public function failure(): View
    {
        return view('payments.failure');
    }
}
