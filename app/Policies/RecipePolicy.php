<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

class RecipePolicy
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

    public function view(User $user, Recipe $recipe): bool
    {
        return ! $recipe->cookbook_id || $user->id === $recipe->cookbook->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->cookbook->user_id;
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->cookbook->user_id;
    }

    public function restore(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->cookbook->user_id;
    }

    public function forceDelete(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->cookbook->user_id;
    }
}
