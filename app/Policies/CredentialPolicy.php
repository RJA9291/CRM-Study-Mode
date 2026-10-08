<?php

namespace App\Policies;

use App\Models\Credential;
use App\Models\User;

/**
 * Locker entries are visible to their owner only — super admins included.
 */
class CredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Credential $credential): bool
    {
        return $credential->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Credential $credential): bool
    {
        return $credential->user_id === $user->id;
    }

    public function delete(User $user, Credential $credential): bool
    {
        return $credential->user_id === $user->id;
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }
}
