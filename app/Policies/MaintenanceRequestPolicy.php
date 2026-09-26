<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, MaintenanceRequest $request): bool
    {
        return $request->tenant_id === $user->id
            || $request->assigned_to === $user->id
            || $user->isStaff();
    }

    public function update(User $user, MaintenanceRequest $request): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $request->tenant_id === $user->id && $request->status === 'pending';
    }
}
