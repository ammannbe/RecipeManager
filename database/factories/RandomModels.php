<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Recipes\Recipe;
use App\Models\Ratings\RatingCriterion;

trait RandomModels
{
    /**
     * Get a random recipe
     *
     * @return \App\Models\Recipes\Recipe
     */
    private function getRandomRecipe(): \App\Models\Recipes\Recipe
    {
        /** @var \App\Models\Recipes\Recipe */
        return Recipe::withoutGlobalScope('isAdminOrOwnOrPublic')
            ->inRandomOrder()
            ->first();
    }

    /**
     * Get a random user
     *
     * @return \App\Models\User
     */
    private function getRandomUser(): \App\Models\User
    {
        return User::inRandomOrder()->first();
    }

    /**
     * Get a random rating criterion
     *
     * @return \App\Models\Ratings\RatingCriterion
     */
    private function getRandomRatingCriterion(): \App\Models\Ratings\RatingCriterion
    {
        return RatingCriterion::inRandomOrder()->first();
    }
}
