<?php

namespace App\Observers;

use App\Models\Unit;

class UnitObserver
{
    /**
     * Handle the unit "saving" event.
     *
     * @param  \App\Models\Unit  $unit
     * @return void
     */
    public function saving(Unit $unit)
    {
        $unit->slugifyName();
    }
}
