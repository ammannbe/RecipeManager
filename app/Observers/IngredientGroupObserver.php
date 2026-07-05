<?php

namespace App\Observers;

use App\Models\Ingredient;
use App\Models\IngredientGroup;

class IngredientGroupObserver
{
    public function deleting(IngredientGroup $ingredientGroup): void
    {
        $ingredientGroup->ingredients()->each(fn (Ingredient $i) => $i->delete());
    }

    public function deleted(IngredientGroup $ingredientGroup): void
    {
        if ($ingredientGroup->trashed()) {
            $ingredientGroup->update(['position' => null]);
        }
    }
}
