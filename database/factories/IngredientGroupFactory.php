<?php

namespace Database\Factories;

use App\Models\IngredientGroup;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngredientGroup>
 */
class IngredientGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'name' => $this->faker->unique(reset: true)->word(),
        ];
    }
}
