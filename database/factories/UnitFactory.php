<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'name_shortcut' => $this->faker->optional()->randomLetter(),
            'name_plural' => $this->faker->optional()->word(),
            'name_plural_shortcut' => $this->faker->optional()->randomLetter(),
        ];
    }
}
