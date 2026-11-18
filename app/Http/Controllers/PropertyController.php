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
        $validated = $this->validateProperty($request, withRooms: true);

        $property = Property::create(Arr::only($validated, ['name', 'address', 'region', 'province', 'city', 'barangay', 'street']));

        $created = $this->rooms->generate($property, (int) $validated['room_count'], (float) $validated['monthly_rate']);

        return redirect()->route('admin.properties.index')
            ->with('status', "Property created with {$created} room(s).");
    }

    public function edit(Property $property): View
    {
        return view('admin.properties.edit', compact('property'));
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $validated = $this->validateProperty($request);

        $property->update(Arr::only($validated, ['name', 'address', 'region', 'province', 'city', 'barangay', 'street']));

        return redirect()->route('admin.properties.index')->with('status', 'Property updated.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $property->delete();

        return redirect()->route('admin.properties.index')->with('status', 'Property deleted.');
    }

    /**
     * A building's rooms are set up once, when the building is added: an
     * existing building does not gain rooms, so editing it only changes its details.
     */
    protected function validateProperty(Request $request, bool $withRooms = false): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:150'],
        ] + ($withRooms ? [
            'room_count' => ['required', 'integer', 'min:1', 'max:200'],
            'monthly_rate' => ['required', 'numeric', 'min:1'],
        ] : []), [
            'room_count.required' => 'Enter how many rooms this building has.',
            'room_count.min' => 'A building needs at least one room.',
            'monthly_rate.required' => 'Enter the monthly rate per room.',
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


        return $validated;
    }

    /** Barangays of one city/municipality, for the property form's dropdown. */
    public function barangays(string $city): JsonResponse
    {
        return response()->json(PhilippineAddress::barangays($city));
    }
}
