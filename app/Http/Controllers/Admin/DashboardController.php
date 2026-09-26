<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'tenants' => User::where('role', 'tenant')->count(),
            'occupied_rooms' => Room::where('status', 'occupied')->count(),
            'vacant_rooms' => Room::where('status', 'vacant')->count(),
            'overdue_payments' => Payment::where('status', 'overdue')->count(),
            'income_this_month' => Payment::where('status', 'paid')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'pending_bookings' => Booking::where('status', 'pending_payment')->count(),
            'pending_transfers' => RoomTransfer::where('status', 'pending')->count(),
            'open_maintenance' => MaintenanceRequest::whereIn('status', ['pending', 'in_progress'])->count(),
        ];

        $activity = $this->recentActivity();

        return view('admin.dashboard', compact('stats', 'activity'));
    }

    protected function recentActivity(): Collection
    {
        $payments = Payment::with(['lease.tenant', 'booking.tenant'])
            ->where('status', 'paid')
            ->latest('paid_at')
            ->take(5)
            ->get()
            ->map(fn (Payment $payment) => [
                'icon' => 'bi-cash-coin',
                'color' => 'success',
                'text' => 'Payment received from '.($payment->lease?->tenant?->name ?? $payment->booking?->tenant?->name ?? 'a tenant').' — ₱'.number_format($payment->amount, 2),
                'at' => $payment->paid_at,
            ]);

        $bookings = Booking::with('tenant')
            ->latest('booked_at')
            ->take(5)
            ->get()
            ->map(fn (Booking $booking) => [
                'icon' => 'bi-calendar-check',
                'color' => 'primary',
                'text' => 'New booking from '.($booking->tenant?->name ?? 'a tenant'),
                'at' => $booking->booked_at,
            ]);

        $maintenance = MaintenanceRequest::with('room')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(fn (MaintenanceRequest $request) => [
                'icon' => 'bi-tools',
                'color' => 'warning',
                'text' => 'Maintenance filed for Room '.($request->room?->room_number ?? '—'),
                'at' => $request->created_at,
            ]);

        $transfers = RoomTransfer::with(['fromRoom', 'toRoom'])
            ->latest('requested_at')
            ->take(5)
            ->get()
            ->map(fn (RoomTransfer $transfer) => [
                'icon' => 'bi-arrow-left-right',
                'color' => 'info',
                'text' => 'Transfer requested: Room '.($transfer->fromRoom?->room_number ?? '—').' → Room '.($transfer->toRoom?->room_number ?? '—'),
                'at' => $transfer->requested_at,
            ]);

        return $payments->concat($bookings)->concat($maintenance)->concat($transfers)
            ->filter(fn ($item) => $item['at'] !== null)
            ->sortByDesc('at')
            ->take(8)
            ->values();
    }
}
