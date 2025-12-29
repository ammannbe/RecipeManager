<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Cookbook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cookbook>
 */
class CookbookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'author_id' => Author::factory(),
        ];
    }
}
