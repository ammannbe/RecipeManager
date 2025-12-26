<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability)
    {
        if ($user->admin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user)
    {
        return false;
    }

    public function view(User $user, User $model)
    {
        return $user->id === $model->id;
    }

    public function create(User $user)
    {
        return true;
    }

    public function update(User $user, User $model)
    {
        return $user->id === $model->id;
    }

    public function delete(User $user, User $model)
    {
        return $user->id === $model->id;
    }

    public function restore(User $user, User $model)
    {
        return $user->id === $model->id;
    }

    public function forceDelete(User $user, User $model)
    {
        return $user->id === $model->id;
    }
}
