<?php

namespace App\Observers\Recipes;

use App\Models\Recipes\Recipe;

class RecipeObserver
{
    /**
     * Handle the recipe "creating" event.
     *
     * @param  \App\Models\Recipes\Recipe  $recipe
     * @return void
     */
    public function creating(Recipe $recipe)
    {
        $recipe->author_id = auth()->user()->author->id;
    }

    /**
     * Handle the recipe "saving" event.
     *
     * @param  \App\Models\Recipes\Recipe  $recipe
     * @return void
     */
    public function saving(Recipe $recipe)
    {
        $recipe->slugifyName();
    }
}
