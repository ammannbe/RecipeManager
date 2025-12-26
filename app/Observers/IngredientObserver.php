<?php

namespace App\Observers;

use App\Models\Ingredient;

class IngredientObserver
{
    public function saving(Ingredient $ingredient): void
    {
        if ($ingredient->ingredient_id) {
            $ingredient->ingredient_group_id = $ingredient->ingredient->ingredient_group_id;
        }
    }

    public function deleted(Ingredient $ingredient): void
    {
        if ($ingredient->trashed()) {
            $ingredient->update(['position' => null]);
        }
    }
}
