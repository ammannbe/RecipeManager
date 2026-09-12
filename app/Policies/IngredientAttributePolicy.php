<?php

namespace App\Policies;

use App\Models\IngredientAttribute;
use App\Models\User;

class IngredientAttributePolicy
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

    public function view(User $user, IngredientAttribute $attribute): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, IngredientAttribute $attribute): bool
    {
        return false;
    }

    public function delete(User $user, IngredientAttribute $attribute): bool
    {
        return $user->admin && $attribute->ingredients->isEmpty();
    }

    public function restore(User $user, IngredientAttribute $attribute): bool
    {
        return false;
    }

    public function forceDelete(User $user, IngredientAttribute $attribute): bool
    {
        return $user->admin && $attribute->ingredients->isEmpty();
    }
}
