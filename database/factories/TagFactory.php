<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Column is only 20 chars, so keep the word short and suffix it to stay unique.
            'name' => \Str::limit($this->faker->word(), 12, '').'-'.$this->faker->unique()->numberBetween(1, 999999),
        ];
    }
}
