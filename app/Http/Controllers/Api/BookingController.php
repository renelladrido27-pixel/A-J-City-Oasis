<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Api\Concerns\HandlesPaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\UserResource;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class BookingController extends Controller
{
    use HandlesPaymentGateway;

    public function __construct(protected BookingService $bookings) {}

    /**
     * Combined "create account & book" for anonymous visitors — the mobile
     * room-booking screen collects account details and the booking in one form
     * (unlike the web app, which requires signing up first). Reuses the same
     * validation/creation rules as Api\AuthController::register and the same
     * BookingService the web app uses.
     *
     * A tenant who is already authenticated (e.g. signed up via C2's Sign up
     * tab, or logged in) skips account creation entirely and just books with
     * their existing account — mirrors web's authenticated BookingController::store.
     */
    public function store(Request $request, Room $room): JsonResponse
    {
        abort_unless($room->isVacant(), 422, 'This room is no longer available.');

        $existingUser = auth('sanctum')->user();

        if ($existingUser) {
            abort_unless($existingUser->isTenant(), 403, 'Only tenants can book rooms.');

            if (! $existingUser->hasVerifiedEmail()) {
                return response()->json(['message' => 'Verify your email address to continue.', 'code' => 'email_unverified'], 403);
            }

            $validated = $request->validate([
                'move_in_date' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(7)->toDateString()],
            ]);

            try {
                $booking = $this->bookings->createBooking($existingUser, $room, $validated['move_in_date'] ?? null);
            } catch (PaymentGatewayException $e) {
                return response()->json(['message' => $e->getMessage()], 502);
            }

            $booking->load(['room.property', 'payments']);

            return response()->json([
                'user' => UserResource::make($existingUser),
                'booking' => BookingResource::make($booking),
            ], 201);
        }

        // New visitor: create the account first. The room isn't reserved until
        // they've entered the emailed code — the app then calls this endpoint
        // again, authenticated, and takes the branch above.
        $user = AuthController::createTenant($request, [
            'move_in_date' => ['nullable', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(7)->toDateString()],
        ]);

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => UserResource::make($user),
            'verification_required' => true,
        ], 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $booking->load(['room.property', 'payments']);

        return response()->json(['booking' => BookingResource::make($booking)]);
    }

    /**
     * Pay the booking's upfront payment. In fake mode, completes immediately
     * (same path the web app's fake-complete test route uses); otherwise
     * returns a Xendit invoice URL for the app to open.
     */
    public function pay(Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $payment = $booking->payments()->where('type', 'booking_upfront')->where('status', 'pending')->first();

        abort_unless($payment, 422, 'This booking has no pending payment.');

        try {
            $result = $this->startOrResumePayment($payment, $booking->tenant->email);
        } catch (PaymentGatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        if ($result['status'] === 'paid') {
            $result['booking'] = BookingResource::make($booking->fresh(['room.property', 'payments']));
        }

        return response()->json($result);
    }

    public function checkStatus(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $payment = $booking->payments()->where('type', 'booking_upfront')->first();

        abort_unless($payment, 404);

        try {
            $result = $this->pollPaymentStatus($payment);
        } catch (PaymentGatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        if ($result['status'] === 'paid') {
            $result['booking'] = BookingResource::make($booking->fresh(['room.property', 'payments']));
        }

        return response()->json($result);
    }
}
