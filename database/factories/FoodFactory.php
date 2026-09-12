<?php

namespace Database\Factories;

use App\Models\Food;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Food>
 */
class FoodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Faker's word pool is smaller than the seeded row count, so suffix it.
            'name' => \Str::limit($this->faker->word(), 40, '').'-'.$this->faker->unique()->numberBetween(1, 999999),
        ];
    }
}
