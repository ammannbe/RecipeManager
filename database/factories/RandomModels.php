<?php

namespace Database\Factories;

use App\Models\RatingCriterion;
use App\Models\Recipe;
use App\Models\User;

trait RandomModels
{
    /**
     * Get a random recipe
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
     */
    private function getRandomUser(): \App\Models\User
    {
        return User::inRandomOrder()->first();
    }

    /**
     * Get a random rating criterion
     */
    private function getRandomRatingCriterion(): \App\Models\RatingCriterion
    {
        return RatingCriterion::inRandomOrder()->first();
    }
}
