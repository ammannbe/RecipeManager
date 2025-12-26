<?php

namespace Database\Factories;

use Database\Factories\RandomModels;
use App\Models\IngredientGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class IngredientGroupFactory extends Factory
{
    use RandomModels;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'recipe_id' => $this->getRandomRecipe()->id,
            'name' => $this->faker->unique(true)->word,
        ];
    }
}
