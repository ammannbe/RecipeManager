<?php

namespace Database\Factories\Ingredients;

use Database\Factories\RandomModels;
use App\Models\Ingredients\IngredientGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class IngredientGroupFactory extends Factory
{
    use RandomModels;

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = IngredientGroup::class;

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
