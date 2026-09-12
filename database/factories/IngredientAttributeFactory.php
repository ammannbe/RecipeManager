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
            // Column is 40 chars, so cap the words before the unique suffix.
            'name' => \Str::limit($this->faker->word().' '.$this->faker->word(), 30, '').' '.$this->faker->unique()->numberBetween(1, 999999),
        ];
    }
}
