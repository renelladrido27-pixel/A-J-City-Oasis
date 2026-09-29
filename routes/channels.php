<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Each user only receives their own notifications.
Broadcast::channel('users.{id}', fn (User $user, int $id) => $user->id === $id);
