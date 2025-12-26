<?php

namespace App\Observers;

use App\Models\Ingredient;
use App\Models\IngredientGroup;
use App\Models\Rating;
use App\Models\Recipe;

class RecipeObserver
{
    public function deleting(Recipe $recipe)
    {
        $recipe->ingredients()->each(fn (Ingredient $i) => $i->delete());
        $recipe->groups()->each(fn (IngredientGroup $g) => $g->delete());
        $recipe->ratings()->each(fn (Rating $r) => $r->delete());
    }
}
