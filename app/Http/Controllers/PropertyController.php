<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\RoomGenerationService;
use App\Support\PhilippineAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function __construct(protected RoomGenerationService $rooms) {}

    public function index(): View
    {
        // Newest first (last in, first out).
        $properties = Property::withCount('rooms')->orderByDesc('id')->get();

        return view('admin.properties.index', compact('properties'));
    }

    public function create(): View
    {
        return view('admin.properties.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProperty($request);

        $property = Property::create(Arr::only($validated, ['name', 'address', 'region', 'province', 'city', 'barangay', 'street']));

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

        $property->update(Arr::only($validated, ['name', 'address', 'region', 'province', 'city', 'barangay', 'street']));

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
            'region' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:150'],
            'room_count' => ['nullable', 'integer', 'min:0', 'max:200'],
            'monthly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        // The dropdowns only offer real places, but check anyway: the city must
        // belong to that province and region, and the barangay to that city.
        $cityCode = PhilippineAddress::cityCode($validated['region'], $validated['province'], $validated['city']);
        if (! $cityCode) {
            throw ValidationException::withMessages(['city' => 'Choose the region, province and city/municipality from the lists.']);
        }
        if (filled($validated['barangay'] ?? null) && ! in_array($validated['barangay'], PhilippineAddress::barangays($cityCode), true)) {
            throw ValidationException::withMessages(['barangay' => 'Choose the barangay from the list.']);
        }
        $validated['address'] = PhilippineAddress::format(
            $validated['street'], $validated['barangay'] ?? null, $validated['city'], $validated['province'],
        );

        if ((int) ($validated['room_count'] ?? 0) > 0 && empty($validated['monthly_rate'])) {
            throw ValidationException::withMessages([
                'monthly_rate' => 'The monthly rate is required when adding rooms.',
            ]);
        }

        return $validated;
    }

    /** Barangays of one city/municipality, for the property form's dropdown. */
    public function barangays(string $city): JsonResponse
    {
        return response()->json(PhilippineAddress::barangays($city));
    }

    protected function generateRoomsIfRequested(Property $property, array $validated): int
    {
        $roomCount = (int) ($validated['room_count'] ?? 0);

        return $roomCount > 0
            ? $this->rooms->generate($property, $roomCount, (float) $validated['monthly_rate'])
            : 0;
    }
}
