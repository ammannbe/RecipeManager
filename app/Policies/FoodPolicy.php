<?php

namespace App\Policies;

use App\Models\Food;
use App\Models\User;

class FoodPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['delete', 'forceDelete'])) {
            return null;
        }

        if ($user->admin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Food $food): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Food $food): bool
    {
        return false;
    }

    public function delete(User $user, Food $food): bool
    {
        return $user->admin && $food->ingredients->isEmpty();
    }

    public function restore(User $user, Food $food): bool
    {
        return false;
    }

    public function forceDelete(User $user, Food $food): bool
    {
        return $user->admin && $food->ingredients->isEmpty();
    }
}
