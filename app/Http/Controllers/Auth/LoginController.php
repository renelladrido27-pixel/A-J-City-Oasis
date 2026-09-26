<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.form', ['activeTab' => 'signin']);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'These credentials do not match our records, or this account has been deactivated.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($this->redirectPathFor(Auth::user()->role));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    protected function redirectPathFor(string $role): string
    {
        return match ($role) {
            'admin' => route('admin.dashboard'),
            'staff' => route('staff.maintenance.index'),
            default => route('tenant.dashboard'),
        };
    }
}
