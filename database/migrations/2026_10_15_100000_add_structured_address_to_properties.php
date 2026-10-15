<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Property addresses are picked from Region → Province → City/Municipality →
 * Barangay dropdowns plus a typed street. `address` stays as the one-line
 * version everything else already displays.
 *
 * Also fills in the real address and room type of the two A&J CITY OASIS
 * buildings (from the owner's listing: "Studio Loft Type Apartment, Maglantay
 * Subd. Bo. 2, Koronadal City") where they still carry the seeded defaults.
 */
return new class extends Migration
{
    private const BUILDINGS = ['A&J CITY OASIS - Main Building', 'A&J CITY OASIS - Annex Building'];

    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('region')->nullable()->after('address');
            $table->string('province')->nullable()->after('region');
            $table->string('city')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('city');
            $table->string('street')->nullable()->after('barangay');
        });

        $buildings = DB::table('properties')
            ->whereIn('name', self::BUILDINGS)
            ->where('address', 'Koronadal City, South Cotabato')
            ->pluck('id');

        DB::table('properties')->whereIn('id', $buildings)->update([
            'region' => 'Region XII (SOCCSKSARGEN)',
            'province' => 'South Cotabato',
            'city' => 'City of Koronadal',
            'street' => 'Maglantay Subd., Bo. 2',
            'address' => 'Maglantay Subd., Bo. 2, City of Koronadal, South Cotabato',
        ]);

        DB::table('rooms')
            ->whereIn('property_id', DB::table('properties')->whereIn('name', self::BUILDINGS)->pluck('id'))
            ->where('type', 'standard')
            ->update(['type' => 'Studio Loft Type']);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['region', 'province', 'city', 'barangay', 'street']);
        });
    }
};
