<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\User;

class CookbookPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->admin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Cookbook $cookbook): bool
    {
        return $user->author_id === $cookbook->author_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Cookbook $cookbook): bool
    {
        return $user->author_id === $cookbook->author_id;
    }

    public function delete(User $user, Cookbook $cookbook): bool
    {
        return $user->author_id === $cookbook->author_id;
    }

    public function restore(User $user, Cookbook $cookbook): bool
    {
        return $user->author_id === $cookbook->author_id;
    }

    public function forceDelete(User $user, Cookbook $cookbook): bool
    {
        return false;
    }
}
