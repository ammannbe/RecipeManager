<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->unique()->word(),
            'name_shortcut' => $this->faker->optional()->randomLetter(),
            'name_plural' => $this->faker->optional()->word(),
            'name_plural_shortcut' => $this->faker->optional()->randomLetter(),
        ];
    }
}
