<?php

namespace App\Policies;

use App\Models\RoomTransfer;
use App\Models\User;

class RoomTransferPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, RoomTransfer $roomTransfer): bool
    {
        return $roomTransfer->lease->tenant_id === $user->id;
    }
}
