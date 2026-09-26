<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'tenant',
        ]);

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => UserResource::make($user),
        ], 201);
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
