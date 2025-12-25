<?php

namespace App\Observers\Ingredients;

use App\Models\Ingredients\Ingredient;

class IngredientObserver
{
    /**
     * Handle the ingredient "saving" event.
     *
     * @param  \App\Models\Ingredients\Ingredient  $ingredient
     * @return void
     */
    public function saving(Ingredient $ingredient): void
    {
        if ($ingredient->isDirty('ingredient_id')) {
            $ingredient->adoptIngredientGroupFromParent();
        }

        if ($ingredient->amount_max == 0) {
            $ingredient->amount_max = null;
        }

        if ($ingredient->amount == 0 && !$ingredient->amount_max) {
            $ingredient->amount = null;
        }

        if ($ingredient->amount_max <= $ingredient->amount) {
            $ingredient->amount_max = null;
        }
    }

    /**
     * Handle the ingredient "deleted" event.
     *
     * @param  \App\Models\Ingredients\Ingredient  $ingredient
     * @return void
     */
    public function deleted(Ingredient $ingredient): void
    {
        if ($ingredient->trashed()) {
            $ingredient->update(['position' => null]);
        }
    }

    /**
     * Handle the ingredient "restored" event.
     *
     * @param  \App\Models\Ingredients\Ingredient  $ingredient
     * @return void
     */
    public function restored(Ingredient $ingredient): void
    {
        $ingredient->updatePosition();
    }
}
