<?php

namespace Database\Factories\Ratings;

use App\Models\Ratings\RatingCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingCriterionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RatingCriterion::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->unique()->word,
        ];
    }
}
