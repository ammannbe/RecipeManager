<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Rating;
use App\Models\RatingCriterion;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rating>
 */
class RatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'author_id' => Author::factory(),
            'rating_criterion_id' => RatingCriterion::factory(),
            'comment' => $this->faker->text(),
            'stars' => rand(0, 5),
        ];
    }
}
