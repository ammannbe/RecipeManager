<?php

namespace App\Observers;

use App\Models\Ingredient;
use App\Models\IngredientGroup;

class IngredientGroupObserver
{
    public function deleting(IngredientGroup $ingredientGroup)
    {
        $ingredientGroup->ingredients()->each(fn (Ingredient $i) => $i->delete());
    }
}
