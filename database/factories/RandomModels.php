<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Recipe;
use App\Models\RatingCriterion;

trait RandomModels
{
    /**
     * Get a random recipe
     *
     * @return \App\Models\Recipe
     */
    private function getRandomRecipe(): \App\Models\Recipe
    {
        /** @var \App\Models\Recipe */
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
     * @return \App\Models\RatingCriterion
     */
    private function getRandomRatingCriterion(): \App\Models\RatingCriterion
    {
        return RatingCriterion::inRandomOrder()->first();
    }
}
