<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Support\AccountRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::once([...$credentials, 'is_active' => true, 'role' => 'tenant'])) {
            return response()->json([
                'message' => 'These credentials do not match our records, or this account has been deactivated.',
            ], 422);
        }

        $user = Auth::user();

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * Standalone sign-up (no room booking attached) — see Api\BookingController::store
     * for the combined "create account & book" flow the room-booking screen uses.
     */
    public function register(Request $request): JsonResponse
    {
        $user = self::createTenant($request);

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => UserResource::make($user),
        ], 201);
    }

    /**
     * Validates sign-up details, creates the tenant account and emails its
     * 6-digit verification code. Shared with Api\BookingController::store,
     * whose room-booking form creates the account as its first step.
     *
     * @param  array<string, mixed>  $extraRules
     */
    public static function createTenant(Request $request, array $extraRules = []): User
    {
        AccountRules::prepare($request);

        $validated = $request->validate([
            ...AccountRules::name(),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => AccountRules::phone(),
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ...$extraRules,
        ], AccountRules::messages());

        $user = User::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => 'tenant',
        ]);

        app(EmailVerificationService::class)->sendCode($user);

        return $user;
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => UserResource::make($request->user())]);
    }
}
