<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Until a super admin exists, the earliest registered account is the owner and is promoted automatically.
 */
class AdminBootstrap
{
    public function promoteIfOwner(User $user): bool
    {
        if (User::where('role', UserRole::SuperAdmin->value)->exists()) {
            return false;
        }

        if ($user->getKey() !== User::min('id')) {
            return false;
        }

        $user->forceFill(['role' => UserRole::SuperAdmin])->save();
        $user->approve();

        return true;
    }
}
