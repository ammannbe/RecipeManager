<?php

namespace App\Observers\Ratings;

use App\Models\Ratings\RatingCriterion;

class RatingCriterionObserver
{
    /**
     * Handle the rating criterion "saving" event.
     *
     * @param  \App\Models\Ratings\RatingCriterion  $ratingCriterion
     * @return void
     */
    public function saving(RatingCriterion $ratingCriterion)
    {
        $ratingCriterion->slugifyName();
    }
}
