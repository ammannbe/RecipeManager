<?php

namespace App\Observers;

use App\Models\Ingredient;

class IngredientObserver
{
    public function saving(Ingredient $ingredient): void
    {
        // The parent is gone while a cascade soft-deletes it, so leave the group as is.
        if ($ingredient->ingredient_id && $ingredient->ingredient !== null) {
            $ingredient->ingredient_group_id = $ingredient->ingredient->ingredient_group_id;
        }

        if (! $ingredient->recipe_id && $ingredient->ingredient_group_id) {
            $ingredient->recipe_id = $ingredient->ingredientGroup?->recipe_id;
        }

        if (! $ingredient->recipe_id && $ingredient->ingredient_id) {
            $ingredient->recipe_id = $ingredient->ingredient?->recipe_id;
        }
    }

    public function deleted(Ingredient $ingredient): void
    {
        if ($ingredient->trashed()) {
            $ingredient->update(['position' => null]);
        }
    }
}
