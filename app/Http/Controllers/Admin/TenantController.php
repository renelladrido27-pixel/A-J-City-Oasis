<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $sort = in_array($request->query('sort'), ['name', 'email']) ? $request->query('sort') : 'name';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $tenants = User::where('role', 'tenant')
            ->when($search, fn ($q) => $q->where(
                fn ($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->with(['leases' => fn ($q) => $q->where('status', 'active')->with('room')])
            ->orderBy($sort, $dir)
            ->paginate(25)
            ->withQueryString();

        return view('admin.tenants.index', compact('tenants', 'search', 'sort', 'dir'));
    }

    public function show(User $tenant): View
    {
        abort_unless($tenant->isTenant(), 404);

        $tenant->load([
            'leases' => fn ($q) => $q->with(['room.property', 'payments', 'maintenanceRequests', 'utilityBills'])->latest(),
        ]);

        return view('admin.tenants.show', compact('tenant'));
    }
}
