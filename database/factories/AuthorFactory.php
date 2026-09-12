<?php

namespace Database\Factories;

use App\Models\Author;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Author>
 */
class AuthorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // authors.name is unique and Faker repeats names, so suffix it.
            'name' => \Str::limit($this->faker->name(), 40, '').' '.$this->faker->unique()->numberBetween(1, 999999),
        ];
    }
}
