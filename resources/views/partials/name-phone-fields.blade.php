{{--
    First / middle / surname + PH mobile number, for the Bootstrap-styled
    account forms (profile, admin create/edit user). Rules: App\Support\AccountRules.
    @include('partials.name-phone-fields', ['user' => $user ?? null, 'phoneRequired' => false])
--}}
@php($user ??= null)
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label class="form-label" for="first_name">First Name</label>
        <input type="text" name="first_name" id="first_name" class="form-control @error('first_name') is-invalid @enderror"
               value="{{ old('first_name', $user?->first_name) }}" maxlength="100" autocomplete="given-name" required>
        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="middle_name">Middle Name <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" name="middle_name" id="middle_name" class="form-control @error('middle_name') is-invalid @enderror"
               value="{{ old('middle_name', $user?->middle_name) }}" maxlength="100" autocomplete="additional-name">
        @error('middle_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="last_name">Surname</label>
        <input type="text" name="last_name" id="last_name" class="form-control @error('last_name') is-invalid @enderror"
               value="{{ old('last_name', $user?->last_name) }}" maxlength="100" autocomplete="family-name" required>
        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3" data-phone-field>
    <label class="form-label" for="phone">Mobile Number @unless ($phoneRequired ?? false)<span class="text-muted fw-normal">(optional)</span>@endunless</label>
    <input type="tel" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror"
           value="{{ old('phone', $user?->phone) }}" placeholder="09171234567" inputmode="tel" autocomplete="tel"
           pattern="(09|\+639)[0-9]{9}" maxlength="13" title="Philippine mobile number, e.g. 09171234567" @if ($phoneRequired ?? false) required @endif>
    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@else<div class="form-text">Philippine mobile number — 09XXXXXXXXX or +639XXXXXXXXX.</div>@enderror
</div>
