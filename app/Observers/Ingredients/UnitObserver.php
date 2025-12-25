<?php

namespace App\Observers\Ingredients;

use App\Models\Ingredients\Unit;

class UnitObserver
{
    /**
     * Handle the unit "saving" event.
     *
     * @param  \App\Models\Ingredients\Unit  $unit
     * @return void
     */
    public function saving(Unit $unit)
    {
        $unit->slugifyName();
    }
}
