<?php

namespace Database\Seeders;

use App\Support\StockRoomPhotos;
use Illuminate\Database\Seeder;

class RoomImageSeeder extends Seeder
{
    /**
     * The owner's real photos on the twelve photographed rooms (the first six
     * on floors 1 and 2 of the first building, with a matching description
     * and inclusions), and a drawn placeholder on every other room so none
     * shows up blank in the listings.
     *
     * Safe to re-run: rooms with their own uploaded photos are left alone.
     */
    public function run(): void
    {
        StockRoomPhotos::syncAll();
    }
}
