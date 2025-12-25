<?php

namespace Database\Factories\Ingredients;

use App\Models\Recipes\Recipe;
use App\Models\Ingredients\Food;
use App\Models\Ingredients\Unit;
use App\Models\Ingredients\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

class IngredientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ingredient::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $recipeIds = Recipe::withoutGlobalScope('isAdminOrOwnOrPublic')->pluck('id')->toArray();
        $recipeId = (int) $this->faker->randomElement($recipeIds);
        $recipe = Recipe::withoutGlobalScope('isAdminOrOwnOrPublic')->find($recipeId);

        $amount = $this->faker->randomElement([null, $this->faker->randomFloat(2, 0, 999)]);
        $amountMax = null;
        if ($amount !== null) {
            $amountMax = $this->faker->randomFloat(2, $amount, 1000);
        }

        return [
            'recipe_id' => $recipe->id,
            'amount' => $amount,
            'amount_max' => $amountMax,
            'unit_id' => $this->faker->randomElement([null, ...Unit::pluck('id')->toArray()]),
            'food_id' => $this->faker->randomElement(Food::pluck('id')->toArray()),
            'ingredient_group_id' => $this->faker->randomElement([null, ...$recipe->ingredientGroups()->pluck('id')->toArray()]),
        ];
    }
}
