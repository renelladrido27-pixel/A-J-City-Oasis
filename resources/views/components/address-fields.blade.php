@props(['property' => null])

{{--
    Region → Province → City/Municipality → Barangay dropdowns, then the street.
    Each list fills in once the one above it is chosen (PSGC data — see
    App\Support\PhilippineAddress). Values are the place names.
--}}
@php
    $value = fn (string $field) => old($field, $property?->{$field});
    $levels = [
        'region' => ['Region', true],
        'province' => ['Province', true],
        'city' => ['City/Municipality', true],
        'barangay' => ['Barangay', false],
    ];
@endphp

<div class="address-fields" data-places-url="{{ asset('data/ph-places.json') }}" data-barangays-url="{{ route('admin.places.barangays', '000000000') }}">
    <div class="row g-3 mb-3">
        @foreach ($levels as $field => [$label, $required])
            <div class="col-sm-6">
                <label class="form-label" for="address-{{ $field }}">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
                <select name="{{ $field }}" id="address-{{ $field }}" data-level="{{ $field }}" data-value="{{ $value($field) }}"
                        class="form-select @error($field) is-invalid @enderror" @required($required) disabled>
                    <option value="">Select {{ $label }}</option>
                    @if ($value($field))
                        <option value="{{ $value($field) }}" selected>{{ $value($field) }}</option>
                    @endif
                </select>
                @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endforeach
    </div>
    <div class="mb-3">
        <label class="form-label" for="address-street">Street / Subdivision <span class="text-danger">*</span></label>
        <input type="text" name="street" id="address-street" class="form-control @error('street') is-invalid @enderror"
               value="{{ $value('street') }}" placeholder="e.g. Maglantay Subd., Bo. 2" maxlength="150" required>
        @error('street')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($property && ! $property->region && $property->address)
            <div class="form-text">Old address: {{ $property->address }} — pick it from the lists above.</div>
        @endif
    </div>
</div>

@once
@push('scripts')
<script>
document.querySelectorAll('.address-fields').forEach(async (box) => {
    const select = (level) => box.querySelector(`[data-level="${level}"]`);
    const [region, province, city, barangay] = ['region', 'province', 'city', 'barangay'].map(select);

    // Replace a dropdown's options; keep `wanted` selected when it is among them.
    const fill = (el, names, wanted) => {
        const placeholder = el.options[0].textContent;
        el.replaceChildren(new Option(placeholder, ''));
        names.forEach((name) => el.add(new Option(name, name, false, name === wanted)));
        el.disabled = names.length === 0;
    };
    const clear = (...els) => els.forEach((el) => fill(el, [], null));

    let places;
    try {
        places = await (await fetch(box.dataset.placesUrl)).json();
    } catch (e) {
        [region, province, city, barangay].forEach((el) => (el.disabled = false));
        return;
    }

    const regionData = () => places.find((r) => r.name === region.value);
    const provinceData = () => regionData()?.provinces.find((p) => p.name === province.value);
    const cityData = () => provinceData()?.cities.find((c) => c.name === city.value);

    const loadBarangays = async (wanted) => {
        const code = cityData()?.code;
        if (!code) return clear(barangay);
        barangay.disabled = true;
        try {
            const names = await (await fetch(box.dataset.barangaysUrl.replace('000000000', code), { headers: { Accept: 'application/json' } })).json();
            fill(barangay, names, wanted);
        } catch (e) {
            clear(barangay);
        }
    };

    region.addEventListener('change', () => {
        fill(province, (regionData()?.provinces ?? []).map((p) => p.name), null);
        clear(city, barangay);
    });
    province.addEventListener('change', () => {
        fill(city, (provinceData()?.cities ?? []).map((c) => c.name), null);
        clear(barangay);
    });
    city.addEventListener('change', () => loadBarangays(null));

    // Start from what was saved (or just submitted).
    fill(region, places.map((r) => r.name), region.dataset.value);
    fill(province, (regionData()?.provinces ?? []).map((p) => p.name), province.dataset.value);
    fill(city, (provinceData()?.cities ?? []).map((c) => c.name), city.dataset.value);
    await loadBarangays(barangay.dataset.value);
});
</script>
@endpush
@endonce
