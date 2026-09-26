<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LeaseController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $leases = $request->user()->isAdmin()
            ? Lease::with(['tenant', 'room.property'])->latest()->paginate(20)
            : Lease::where('tenant_id', $request->user()->id)->with('room.property')->latest()->paginate(20);

        if ($request->user()->isTenant()) {
            $this->notifications->markTypesRead($request->user(), ['lease', 'transfer', 'move_out']);
        } elseif ($request->user()->isAdmin()) {
            $this->notifications->markTypesRead($request->user(), ['lease', 'move_out']);
        }

        return view('leases.index', compact('leases'));
    }

    public function show(Lease $lease): View
    {
        $this->authorize('view', $lease);

        $lease->load(['payments', 'utilityBills', 'maintenanceRequests', 'roomTransfers', 'moveOut', 'room.property', 'tenant']);

        return view('leases.show', compact('lease'));
    }

    public function uploadDocument(Request $request, Lease $lease): RedirectResponse
    {
        $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        if ($lease->document) {
            Storage::disk('public')->delete($lease->document);
        }

        $lease->update(['document' => $request->file('document')->store('lease-documents', 'public')]);

        return back()->with('status', 'Lease agreement uploaded.');
    }

    public function destroyDocument(Lease $lease): RedirectResponse
    {
        if ($lease->document) {
            Storage::disk('public')->delete($lease->document);
            $lease->update(['document' => null]);
        }

        return back()->with('status', 'Lease agreement removed.');
    }
}
