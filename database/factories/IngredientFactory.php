<?php

namespace Database\Factories;

use App\Models\Food;
use App\Models\Recipe;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class IngredientFactory extends Factory
{
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
