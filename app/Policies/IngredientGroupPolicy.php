<?php

namespace App\Policies;

use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\User;

class IngredientGroupPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->admin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user, Recipe $recipe): bool
    {
        return true;
    }

    public function view(User $user, IngredientGroup $group): bool
    {
        return true;
    }

    public function create(User $user, IngredientGroup $group): bool
    {
        return $user->id === $group->recipe->user_id;
    }

    public function update(User $user, IngredientGroup $group): bool
    {
        return $user->id === $group->recipe->user_id;
    }

    public function delete(User $user, IngredientGroup $group): bool
    {
        return $user->id === $group->recipe->user_id;
    }

    public function restore(User $user, IngredientGroup $group): bool
    {
        return $user->id === $group->recipe->user_id;
    }

    public function forceDelete(User $user, IngredientGroup $group): bool
    {
        return $user->id === $group->recipe->user_id;
    }
}
