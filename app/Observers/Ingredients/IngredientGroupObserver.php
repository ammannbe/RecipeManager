<?php

namespace App\Observers\Ingredients;

use App\Models\Ingredients\IngredientGroup;

class IngredientGroupObserver
{
    /**
     * Handle the ingredientGroup "saving" event.
     *
     * @param  \App\Models\Ingredients\IngredientGroup  $ingredientGroup
     * @return void
     */
    public function saving(IngredientGroup $ingredientGroup)
    {
        $ingredientGroup->slugifyName();
    }
}
