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
        return $recipe->isPublished()
            || $user->author_id === $recipe->author_id
            || $this->cookbookGrants($user, $recipe, 'can_read');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $user->author_id === $recipe->author_id
            || $this->cookbookGrants($user, $recipe, 'can_update');
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $user->author_id === $recipe->author_id
            || $this->cookbookGrants($user, $recipe, 'can_delete');
    }

    public function restore(User $user, Recipe $recipe): bool
    {
        return $this->delete($user, $recipe);
    }

    public function forceDelete(User $user, Recipe $recipe): bool
    {
        return $this->delete($user, $recipe);
    }

    /**
     * Grants on the containing cookbook apply to every recipe in it, whoever wrote it.
     */
    private function cookbookGrants(User $user, Recipe $recipe, string $grant): bool
    {
        return (bool) $recipe->cookbook?->grants($user, $grant);
    }
}
