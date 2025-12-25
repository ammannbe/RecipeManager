<?php

namespace Database\Factories\Ratings;

use App\Models\Ratings\Rating;
use Database\Factories\RandomModels;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingFactory extends Factory
{
    use RandomModels;

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Rating::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'recipe_id' => $this->getRandomRecipe()->id,
            'user_id' => $this->faker->randomElement([null, $this->getRandomUser()->id]),
            'rating_criterion_id' => $this->getRandomRatingCriterion()->id,
            'comment' => $this->faker->text,
            'stars' => rand(0, 5),
        ];
    }
}
