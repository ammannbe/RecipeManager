<?php

namespace App\Observers\Ratings;

use App\Models\Ratings\Rating;

class RatingObserver
{
    /**
     * Handle the Rating "creating" event.
     *
     * @param  \App\Models\Ratings\Rating  $rating
     * @return void
     */
    public function creating(Rating $rating)
    {
        $rating->user_id = (int) auth()->id();

        if (!$rating->author_id) {
            $rating->author_id = auth()->user()->author->id;
        }
    }
}
