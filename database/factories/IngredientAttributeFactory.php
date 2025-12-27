<?php

namespace Database\Factories;

use App\Models\IngredientAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngredientAttribute>
 */
class IngredientAttributeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique(reset: true)->words(asText: true),
        ];
    }
}
