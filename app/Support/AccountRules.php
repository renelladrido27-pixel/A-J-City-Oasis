<?php

namespace App\Support;

/**
 * Validation rules shared by every place an account is created or edited
 * (web sign-up, profile, admin user screens, the mobile API) so the name,
 * phone and password requirements can't drift apart.
 */
class AccountRules
{
    /** Letters (incl. ñ/accents), spaces, periods, apostrophes and hyphens. */
    private const NAME_PATTERN = "/^[\\pL][\\pL\\s.'\\-]*$/u";

    /** PH mobile: 09XXXXXXXXX or +639XXXXXXXXX (spaces/dashes are stripped first). */
    private const PHONE_PATTERN = '/^(09|\+639)\d{9}$/';

    /**
     * @return array<string, array<int, string>>
     */
    public static function name(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', 'regex:'.self::NAME_PATTERN],
            'middle_name' => ['nullable', 'string', 'max:100', 'regex:'.self::NAME_PATTERN],
            'last_name' => ['required', 'string', 'max:100', 'regex:'.self::NAME_PATTERN],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function phone(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:'.self::PHONE_PATTERN];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'first_name.regex' => 'The first name may only contain letters, spaces, periods, apostrophes and hyphens.',
            'middle_name.regex' => 'The middle name may only contain letters, spaces, periods, apostrophes and hyphens.',
            'last_name.regex' => 'The surname may only contain letters, spaces, periods, apostrophes and hyphens.',
            'last_name.required' => 'The surname field is required.',
            'phone.regex' => 'Enter a valid Philippine mobile number, e.g. 09171234567 or +639171234567.',
        ];
    }

    /**
     * Call before $request->validate(): normalizes the phone number in place.
     */
    public static function prepare(\Illuminate\Http\Request $request): void
    {
        if ($request->has('phone')) {
            $request->merge(['phone' => self::normalizePhone($request->input('phone'))]);
        }
    }

    /**
     * "0917 123-4567" → "09171234567"; "+63 917…" → "09171234567". Run before
     * validating so harmless formatting doesn't cause a rejection, and so the
     * stored number is always in one format.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/[\s\-().]/', '', $phone);

        return preg_replace('/^\+63/', '0', $digits);
    }
}
