<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Support\AccountRules;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.form', ['activeTab' => 'signup']);
    }

    public function store(Request $request): RedirectResponse
    {
        AccountRules::prepare($request);

        $validated = $request->validate([
            ...AccountRules::name(),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => AccountRules::phone(),
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], AccountRules::messages());

        $user = User::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'photo' => $request->hasFile('photo') ? $request->file('photo')->store('profiles', 'public') : null,
            'password' => Hash::make($validated['password']),
            'role' => 'tenant',
        ]);

        event(new Registered($user));

        Auth::login($user);

        // The account exists but can't book or use the portal until the
        // emailed 6-digit code is entered.
        app(EmailVerificationService::class)->sendCode($user);

        return redirect()->route('verification.notice');
    }
}
