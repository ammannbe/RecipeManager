<?php

namespace App\Observers;

use App\Models\IngredientGroup;

class IngredientGroupObserver
{
    /**
     * Handle the ingredientGroup "saving" event.
     *
     * @param  \App\Models\IngredientGroup  $ingredientGroup
     * @return void
     */
    public function saving(IngredientGroup $ingredientGroup)
    {
        $ingredientGroup->slugifyName();
    }
}
