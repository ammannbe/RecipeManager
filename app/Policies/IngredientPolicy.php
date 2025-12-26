<?php

namespace App\Policies;

use App\Models\Ingredient;
use App\Models\User;

class IngredientPolicy
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

    public function view(User $user, Ingredient $ingredient): bool
    {
        return true;
    }

    public function create(User $user, Ingredient $ingredient): bool
    {
        return $user->id === $ingredient->recipe->user_id;
    }

    public function update(User $user, Ingredient $ingredient): bool
    {
        return $user->id === $ingredient->recipe->user_id;
    }

    public function delete(User $user, Ingredient $ingredient): bool
    {
        return $user->id === $ingredient->recipe->user_id;
    }

    public function restore(User $user, Ingredient $ingredient): bool
    {
        return $user->id === $ingredient->recipe->user_id;
    }

    public function forceDelete(User $user, Ingredient $ingredient): bool
    {
        return $user->id === $ingredient->recipe->user_id;
    }
}
