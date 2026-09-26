<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\Property;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $announcements = Announcement::with(['author', 'property', 'tenant'])
            ->latest()
            ->paginate(20);

        $properties = Property::orderBy('name')->get();
        $tenants = User::where('role', 'tenant')->orderBy('name')->get();

        $this->notifications->markTypesRead($request->user(), ['announcement']);

        return view('admin.announcements.index', compact('announcements', 'properties', 'tenants'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(['all', 'property', 'floor', 'tenant'])],
            'property_id' => ['required_if:audience,property,floor', 'nullable', 'exists:properties,id'],
            'floor' => ['required_if:audience,floor', 'nullable', 'integer', 'min:1'],
            'tenant_id' => ['required_if:audience,tenant', 'nullable', 'exists:users,id'],
        ]);

        $announcement = Announcement::create([
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'audience' => $validated['audience'],
            'property_id' => in_array($validated['audience'], ['property', 'floor']) ? $validated['property_id'] : null,
            'floor' => $validated['audience'] === 'floor' ? $validated['floor'] : null,
            'tenant_id' => $validated['audience'] === 'tenant' ? $validated['tenant_id'] : null,
        ]);

        $this->targetedTenants($announcement)->each(function (User $tenant) use ($announcement) {
            $this->notifications->notify($tenant, $announcement->title, $announcement->body, 'announcement');
            Mail::to($tenant->email)->send(new AnnouncementMail($announcement, $tenant));
        });

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement posted.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('status', 'Announcement deleted.');
    }

    protected function targetedTenants(Announcement $announcement): Collection
    {
        return match ($announcement->audience) {
            'all' => User::where('role', 'tenant')->get(),
            'tenant' => User::where('id', $announcement->tenant_id)->get(),
            'property' => User::where('role', 'tenant')
                ->whereHas('leases', fn ($q) => $q->where('status', 'active')
                    ->whereHas('room', fn ($r) => $r->where('property_id', $announcement->property_id)))
                ->get(),
            'floor' => User::where('role', 'tenant')
                ->whereHas('leases', fn ($q) => $q->where('status', 'active')
                    ->whereHas('room', fn ($r) => $r->where('property_id', $announcement->property_id)
                        ->where('floor', $announcement->floor)))
                ->get(),
            default => collect(),
        };
    }
}
