<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\EmailVerificationService;
use App\Support\AccountRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => UserResource::make($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        AccountRules::prepare($request);

        $validated = $request->validate([
            ...AccountRules::name(),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => AccountRules::phone(),
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'current_password' => ['required_with:password', 'current_password'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], AccountRules::messages());

        $user->fill([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);

        // A new email address has to be verified again (the app shows its code screen).
        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if ($request->hasFile('photo')) {
            $user->photo = $request->file('photo')->store('profiles', 'public');
        }

        $user->save();

        if ($emailChanged) {
            app(EmailVerificationService::class)->sendCode($user);
        }

        return response()->json(['user' => UserResource::make($user)]);
    }
}
