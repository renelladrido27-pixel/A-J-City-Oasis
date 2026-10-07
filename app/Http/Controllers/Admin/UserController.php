<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccountRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        AccountRules::prepare($request);

        $validated = $request->validate([
            ...AccountRules::name(),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => AccountRules::phone(required: false),
            'role' => ['required', Rule::in(['admin', 'staff', 'tenant'])],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], AccountRules::messages());

        User::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
            // An account the admin creates in person is already vouched for.
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Account created.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        AccountRules::prepare($request);

        $validated = $request->validate([
            ...AccountRules::name(),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => AccountRules::phone(required: false),
            'role' => ['required', Rule::in(['admin', 'staff', 'tenant'])],
        ], AccountRules::messages());

        $user->update($validated);

        return redirect()->route('admin.users.index')->with('status', 'Account updated.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate your own account.');

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', $user->is_active ? 'Account reactivated.' : 'Account deactivated.');
    }
}
