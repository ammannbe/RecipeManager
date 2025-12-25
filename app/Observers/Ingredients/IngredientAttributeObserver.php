<?php

namespace App\Observers\Ingredients;

use App\Models\Ingredients\IngredientAttribute;

class IngredientAttributeObserver
{
    /**
     * Handle the ingredientAttribute "saving" event.
     *
     * @param  \App\Models\Ingredients\IngredientAttribute  $ingredientAttribute
     * @return void
     */
    public function saving(IngredientAttribute $ingredientAttribute)
    {
        $ingredientAttribute->slugifyName();
    }
}
