<?php

namespace App\Http\Controllers;

use App\Mail\UtilityBillEncodedMail;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\UtilityBill;
use App\Services\MailService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UtilityBillController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $bills = $request->user()->isAdmin()
            ? UtilityBill::with('lease.tenant', 'lease.room')->latest()->paginate(25)
            : UtilityBill::whereHas('lease', fn ($q) => $q->where('tenant_id', $request->user()->id))
                ->with('lease.room')
                ->latest()
                ->paginate(25);

        if ($request->user()->isAdmin()) {
            $this->notifications->markTypesRead($request->user(), ['utility']);
        }

        return view('utility-bills.index', compact('bills'));
    }

    public function create(Lease $lease): View
    {
        return view('utility-bills.create', compact('lease'));
    }

    /**
     * Utility bills must be encoded at least 10 days before the due date.
     */
    public function store(Request $request, Lease $lease): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $this->createBill($lease, $validated);

        return redirect()->route('admin.utility-bills.index')->with('status', 'Utility bill encoded.');
    }

    /**
     * Standalone entry point from the Utility Bills index page — lets the admin
     * pick which room/tenant the bill is for instead of drilling into a lease first.
     */
    public function createAny(): View
    {
        $leases = Lease::where('status', 'active')->with(['room.property', 'tenant'])->get()
            ->sortBy(fn (Lease $lease) => $lease->room->room_number);

        return view('utility-bills.create-any', compact('leases'));
    }

    public function storeAny(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lease_id' => ['required', 'exists:leases,id'],
            ...$this->rules(),
        ]);

        $lease = Lease::where('status', 'active')->findOrFail($validated['lease_id']);

        $this->createBill($lease, $validated);

        return redirect()->route('admin.utility-bills.index')->with('status', 'Utility bill encoded.');
    }

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['water', 'electricity'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date', 'after_or_equal:'.now()->addDays(10)->toDateString()],
            'receipt_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    protected function createBill(Lease $lease, array $validated): UtilityBill
    {
        $bill = UtilityBill::create([
            'lease_id' => $lease->id,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'receipt_photo' => request()->hasFile('receipt_photo')
                ? request()->file('receipt_photo')->store('utility-receipts', 'public')
                : null,
            'due_date' => $validated['due_date'],
            'encoded_by' => request()->user()->id,
            'status' => 'unpaid',
        ]);

        $payment = Payment::create([
            'lease_id' => $lease->id,
            'type' => 'utility',
            'amount' => $bill->amount,
            'due_date' => $bill->due_date,
            'status' => 'pending',
        ]);

        $bill->update(['payment_id' => $payment->id]);

        $this->notifications->notify(
            $lease->tenant,
            'New utility bill',
            ucfirst($bill->type)." bill of ₱".number_format($bill->amount, 2).' is due on '.$bill->due_date->format('M d, Y').'.',
            'utility',
        );

        app(MailService::class)->send($lease->tenant->email, new UtilityBillEncodedMail($bill->fresh(['lease.tenant', 'lease.room'])));

        return $bill;
    }
}
