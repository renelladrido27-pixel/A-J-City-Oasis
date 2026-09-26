<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;

class LeasePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Lease $lease): bool
    {
        return $lease->tenant_id === $user->id;
    }
}
