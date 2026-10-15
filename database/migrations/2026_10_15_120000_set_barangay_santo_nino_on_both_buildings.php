<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The owner confirmed "Bo. 2" is Barangay Santo Niño, and that the Annex is at
 * the same address as the Main Building. Only touches the two buildings while
 * they still have the address the previous migration filled in.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('properties')
            ->whereIn('name', ['A&J CITY OASIS - Main Building', 'A&J CITY OASIS - Annex Building'])
            ->where('street', 'Maglantay Subd., Bo. 2')
            ->whereNull('barangay')
            ->update([
                'barangay' => 'Santo Niño',
                'address' => 'Maglantay Subd., Bo. 2, Santo Niño, City of Koronadal, South Cotabato',
            ]);
    }

    public function down(): void
    {
        DB::table('properties')
            ->whereIn('name', ['A&J CITY OASIS - Main Building', 'A&J CITY OASIS - Annex Building'])
            ->where('barangay', 'Santo Niño')
            ->update([
                'barangay' => null,
                'address' => 'Maglantay Subd., Bo. 2, City of Koronadal, South Cotabato',
            ]);
    }
};
