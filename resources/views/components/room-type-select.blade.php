@props(['value' => null])

{{--
    Room type as a dropdown (the types already in use, "Studio Loft Type"
    first). "Other…" reveals a box to type a new one.
--}}
@php
    $options = \App\Models\Room::typeOptions();
    $other = \App\Http\Controllers\RoomController::OTHER_TYPE;
    $current = old('type', $value ?? \App\Models\Room::DEFAULT_TYPE);
    $isOther = $current === $other || ($current && ! in_array($current, $options, true));
    $typed = old('type_other', $isOther && $current !== $other ? $current : '');
@endphp

<div class="room-type-select">
    <label class="form-label" for="room-type">Type</label>
    <select name="type" id="room-type" class="form-select @error('type') is-invalid @enderror" required
            onchange="this.closest('.room-type-select').querySelector('.room-type-other').classList.toggle('d-none', this.value !== '{{ $other }}')">
        @foreach ($options as $option)
            <option value="{{ $option }}" @selected(! $isOther && $current === $option)>{{ ucfirst($option) }}</option>
        @endforeach
        <option value="{{ $other }}" @selected($isOther)>Other…</option>
    </select>
    <input type="text" name="type_other" class="form-control mt-2 room-type-other @if (! $isOther) d-none @endif" value="{{ $typed }}" maxlength="50" placeholder="New room type">
    @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
