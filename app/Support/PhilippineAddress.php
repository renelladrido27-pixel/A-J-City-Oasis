<?php

namespace App\Support;

/**
 * Region → Province → City/Municipality → Barangay, from the PSA's Philippine
 * Standard Geographic Code (PSGC). The address dropdowns on the property form
 * read the same files, so what the admin can pick is exactly what passes here.
 *
 * - public/data/ph-places.json: regions, their provinces and cities (small;
 *   the browser loads it whole).
 * - resources/data/ph-barangays.json: barangays per city code (larger; served
 *   one city at a time).
 *
 * Cities with no province (Metro Manila, Isabela, Cotabato City) sit under a
 * "Metro Manila" / "Independent cities" entry so every address has four levels.
 */
class PhilippineAddress
{
    private static ?array $places = null;

    private static ?array $barangays = null;

    /** @return list<array{name: string, provinces: list<array{name: string, cities: list<array{code: string, name: string}>}>}> */
    public static function places(): array
    {
        return self::$places ??= json_decode(file_get_contents(public_path('data/ph-places.json')), true);
    }

    /** @return list<string> */
    public static function barangays(string $cityCode): array
    {
        self::$barangays ??= json_decode(file_get_contents(resource_path('data/ph-barangays.json')), true);

        return self::$barangays[$cityCode] ?? [];
    }

    /**
     * The city's code when region, province and city really belong together,
     * otherwise null.
     */
    public static function cityCode(?string $region, ?string $province, ?string $city): ?string
    {
        foreach (self::places() as $r) {
            if ($r['name'] !== $region) {
                continue;
            }
            foreach ($r['provinces'] as $p) {
                if ($p['name'] !== $province) {
                    continue;
                }
                foreach ($p['cities'] as $c) {
                    if ($c['name'] === $city) {
                        return $c['code'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * One line for lists, receipts and the app: "Street, Barangay, City, Province".
     * The region is left off, as on a normal Philippine address.
     */
    public static function format(?string $street, ?string $barangay, ?string $city, ?string $province): string
    {
        $province = $province === 'Independent cities' ? null : $province;

        return collect([$street, $barangay, $city, $province])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(', ');
    }
}
