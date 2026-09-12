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
            // Column is only 20 chars, so keep the word short and suffix it to stay unique.
            'name' => \Str::limit($this->faker->word(), 12, '').'-'.$this->faker->unique()->numberBetween(1, 999999),
            'author_id' => Author::factory(),
        ];
    }
}
