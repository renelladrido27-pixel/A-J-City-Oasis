<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\RoomGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function __construct(protected RoomGenerationService $rooms) {}

    public function index(): View
    {
        $properties = Property::withCount('rooms')->get();

        return view('admin.properties.index', compact('properties'));
    }

    public function create(): View
    {
        return view('admin.properties.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProperty($request);

        $property = Property::create([
            'name' => $validated['name'],
            'address' => $validated['address'],
        ]);

        $created = $this->generateRoomsIfRequested($property, $validated);

        return redirect()->route('admin.properties.index')
            ->with('status', $created > 0 ? "Property created with {$created} room(s)." : 'Property created.');
    }

    public function edit(Property $property): View
    {
        return view('admin.properties.edit', compact('property'));
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $validated = $this->validateProperty($request);

        $property->update([
            'name' => $validated['name'],
            'address' => $validated['address'],
        ]);

        $created = $this->generateRoomsIfRequested($property, $validated);

        return redirect()->route('admin.properties.index')
            ->with('status', $created > 0 ? "Property updated, {$created} room(s) added." : 'Property updated.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $property->delete();

        return redirect()->route('admin.properties.index')->with('status', 'Property deleted.');
    }

    protected function validateProperty(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'room_count' => ['nullable', 'integer', 'min:0', 'max:200'],
            'monthly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ((int) ($validated['room_count'] ?? 0) > 0 && empty($validated['monthly_rate'])) {
            throw ValidationException::withMessages([
                'monthly_rate' => 'The monthly rate is required when adding rooms.',
            ]);
        }

        return $validated;
    }

    protected function generateRoomsIfRequested(Property $property, array $validated): int
    {
        $roomCount = (int) ($validated['room_count'] ?? 0);

        return $roomCount > 0
            ? $this->rooms->generate($property, $roomCount, (float) $validated['monthly_rate'])
            : 0;
    }
}
