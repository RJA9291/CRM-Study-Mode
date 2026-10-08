<?php

namespace App\Policies;

use App\Models\Todo;
use App\Models\User;

class TodoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Todo $todo): bool
    {
        return $todo->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Todo $todo): bool
    {
        return $todo->user_id === $user->id;
    }

    public function delete(User $user, Todo $todo): bool
    {
        return $todo->user_id === $user->id;
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }
}
