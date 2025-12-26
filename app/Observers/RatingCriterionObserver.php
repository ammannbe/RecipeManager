<?php

namespace App\Observers;

use App\Models\RatingCriterion;

class RatingCriterionObserver
{
    /**
     * Handle the rating criterion "saving" event.
     *
     * @param  \App\Models\RatingCriterion  $ratingCriterion
     * @return void
     */
    public function saving(RatingCriterion $ratingCriterion)
    {
        $ratingCriterion->slugifyName();
    }
}
