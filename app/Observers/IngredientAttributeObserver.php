<?php

namespace App\Observers;

use App\Models\IngredientAttribute;

class IngredientAttributeObserver
{
    /**
     * Handle the ingredientAttribute "saving" event.
     *
     * @param  \App\Models\IngredientAttribute  $ingredientAttribute
     * @return void
     */
    public function saving(IngredientAttribute $ingredientAttribute)
    {
        $ingredientAttribute->slugifyName();
    }
}
