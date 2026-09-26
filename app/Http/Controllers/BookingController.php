<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentGatewayException;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings, protected NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $bookings = $request->user()->isAdmin()
            ? Booking::with(['tenant', 'room.property'])->latest()->paginate(20)
            : Booking::where('user_id', $request->user()->id)->with('room.property')->latest()->paginate(20);

        if ($request->user()->isTenant() || $request->user()->isAdmin()) {
            $this->notifications->markTypesRead($request->user(), ['booking']);
        }

        return view('bookings.index', compact('bookings'));
    }

    public function create(Room $room): View
    {
        abort_unless($room->isVacant(), 422, 'This room is not available.');

        $room->load('property');

        return view('bookings.create', compact('room'));
    }

    public function store(Request $request, Room $room): RedirectResponse
    {
        abort_unless($room->isVacant(), 422, 'This room is not available.');

        $validated = $request->validate([
            'move_in_date' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(7)->toDateString()],
            'agreed_to_terms' => ['accepted'],
        ], [
            'agreed_to_terms.accepted' => 'You must read and agree to the Rental Agreement before booking.',
            'move_in_date.before_or_equal' => 'Move-in date must be within 7 days of booking.',
        ]);

        try {
            $booking = $this->bookings->createBooking($request->user(), $room, $validated['move_in_date'] ?? null);
        } catch (PaymentGatewayException $e) {
            return back()->withErrors(['gateway' => $e->getMessage()]);
        }

        return redirect()->route('tenant.bookings.show', $booking)
            ->with('status', 'Booking created. Complete the upfront payment via Xendit to confirm your room.');
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);

        return view('bookings.show', compact('booking'));
    }

    public function selectMoveInDate(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'move_in_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $this->bookings->setMoveInDate($booking, $validated['move_in_date']);

        return back()->with('status', 'Move-in date saved.');
    }

    public function cancel(Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        $this->bookings->cancelBooking($booking);

        return redirect()->route('tenant.bookings.index')->with('status', 'Booking cancelled and refunded.');
    }
}
