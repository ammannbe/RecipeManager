<?php

namespace App\Observers\Ingredients;

use App\Models\Ingredients\Food;

class FoodObserver
{
    /**
     * Handle the food "saving" event.
     *
     * @param  \App\Models\Ingredients\Food  $food
     * @return void
     */
    public function saving(Food $food)
    {
        $food->slugifyName();
    }
}
