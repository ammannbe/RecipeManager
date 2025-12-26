<?php

namespace Database\Factories;

use App\Models\Food;
use App\Models\IngredientGroup;
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
        return [
            'recipe_id' => Recipe::factory(),
            'amount' => $this->faker->optional()->randomNumber(3),
            'amount_max' => $this->faker->optional()->randomElement([function (array $attributes) {
                return $attributes['amount'] + $this->faker->randomNumber(2);
            }]),
            'unit_id' => $this->faker->optional()->randomElement([Unit::factory()]),
            'food_id' => $this->faker->randomElement([Food::factory()]),
            'ingredient_group_id' => function (array $attributes) {
                return $this->faker->randomElement(
                    IngredientGroup::whereRecipeId($attributes['recipe_id'])->pluck('id')
                );
            },
        ];
    }
}
