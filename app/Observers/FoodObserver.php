<?php

namespace App\Observers;

use App\Models\Food;

class FoodObserver
{
    /**
     * Handle the food "saving" event.
     *
     * @param  \App\Models\Food  $food
     * @return void
     */
    public function saving(Food $food)
    {
        $food->slugifyName();
    }
}
