<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomImage extends Model
{
    protected $fillable = ['room_id', 'path', 'sort_order'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Built from the current request's host, not the APP_URL config value,
     * so it works whether the app is served via `php artisan serve` on a
     * non-standard port, XAMPP on port 80, or a real domain in production.
     */
    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
