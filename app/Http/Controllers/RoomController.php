<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    /** The "Other…" choice in the room type dropdown. */
    public const OTHER_TYPE = '__other';

    public function browse(Request $request): View
    {
        $properties = Property::orderBy('name')->get();

        $rooms = Room::where('status', 'vacant')
            ->when($request->filled('property_id'), fn ($q) => $q->where('property_id', $request->integer('property_id')))
            ->with(['property', 'images'])
            ->orderBy('property_id')->orderBy('floor')->orderBy('room_number')
            ->get();

        return view('rooms.browse', compact('rooms', 'properties'));
    }

    public function show(Room $room): View
    {
        $room->load(['property', 'images']);

        $similarAvailable = Room::where('property_id', $room->property_id)
            ->where('type', $room->type)
            ->where('status', 'vacant')
            ->where('id', '!=', $room->id)
            ->count();

        return view('rooms.show', compact('room', 'similarAvailable'));
    }

    public function index(Request $request): View
    {
        $properties = Property::orderBy('name')->get();

        $rooms = Room::with(['property', 'images', 'activeLease.tenant', 'reservingBooking.tenant'])
            ->when($request->filled('property_id'), fn ($q) => $q->where('property_id', $request->integer('property_id')))
            // Newest first (last in, first out) — a room just added is at the top.
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.rooms.index', compact('rooms', 'properties'));
    }

    public function create(): View
    {
        $properties = Property::all();

        return view('admin.rooms.create', compact('properties'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRoom($request);

        $room = Room::create([
            ...$validated,
            'inclusions' => $this->parseInclusions($request->input('inclusions')),
        ]);

        $this->storeUploadedImages($request, $room);

        return redirect()->route('admin.rooms.index')->with('status', 'Room created.');
    }

    public function edit(Room $room): View
    {
        $properties = Property::all();
        $room->load('images');

        return view('admin.rooms.edit', compact('room', 'properties'));
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $validated = $this->validateRoom($request, $room);

        $room->update([
            ...$validated,
            'inclusions' => $this->parseInclusions($request->input('inclusions')),
        ]);

        $this->storeUploadedImages($request, $room);

        return redirect()->route('admin.rooms.edit', $room)->with('status', 'Room updated.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        $room->images->each->removeWithFile();

        $room->delete();

        return redirect()->route('admin.rooms.index')->with('status', 'Room deleted.');
    }

    public function destroyImage(Room $room, RoomImage $image): RedirectResponse
    {
        abort_unless($image->room_id === $room->id, 404);

        $image->removeWithFile();

        return back()->with('status', 'Image removed.');
    }

    /**
     * The room number identifies the room inside its building, so it must be
     * unique there ("Room 103" is saved as "103"). The type comes from the
     * dropdown, or the "Other" box when the admin types a new one.
     */
    protected function validateRoom(Request $request, ?Room $room = null): array
    {
        $request->merge([
            'room_number' => Room::normalizeNumber((string) $request->input('room_number')),
            'type' => $request->input('type') === self::OTHER_TYPE ? trim((string) $request->input('type_other')) : $request->input('type'),
        ]);

        return $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'room_number' => [
                'required', 'string', 'max:20',
                Rule::unique('rooms', 'room_number')->where('property_id', $request->input('property_id'))->ignore($room),
            ],
            'floor' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', 'max:50'],
            'size_sqm' => ['nullable', 'integer', 'min:1', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'monthly_rate' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:vacant,occupied,maintenance,reserved'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'room_number.unique' => 'That building already has a room with this number.',
            'type.required' => 'Choose a room type, or type a new one under Other.',
        ]);
    }

    protected function parseInclusions(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        return collect(explode("\n", $raw))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    protected function storeUploadedImages(Request $request, Room $room): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $nextOrder = $room->images()->max('sort_order') + 1;

        foreach ($request->file('images') as $file) {
            $room->images()->create([
                'path' => $file->store('rooms', 'public'),
                'sort_order' => $nextOrder++,
            ]);
        }
    }
}
