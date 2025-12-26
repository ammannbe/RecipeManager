<?php

namespace Database\Factories;

use App\Models\RatingCriterion;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'recipe_id' => Recipe::factory(),
            'user_id' => User::factory(),
            'rating_criterion_id' => RatingCriterion::factory(),
            'comment' => $this->faker->text(),
            'stars' => rand(0, 5),
        ];
    }
}
