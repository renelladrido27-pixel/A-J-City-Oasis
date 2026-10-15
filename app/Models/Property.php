<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'region', 'province', 'city', 'barangay', 'street'];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Which building, without the business name in front:
     * "A&J CITY OASIS - Main Building" → "Main Building".
     */
    public function shortName(): string
    {
        $short = trim(preg_replace('/^A&J CITY OASIS\s*[-–—:]\s*/i', '', $this->name));

        return $short !== '' ? $short : $this->name;
    }
}
