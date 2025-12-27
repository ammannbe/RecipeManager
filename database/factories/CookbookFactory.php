<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Cookbook;
use App\Models\User;
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
            'user_id' => User::factory(),
            'author_id' => function (array $attributes) {
                return $this->faker->randomElement(
                    Author::whereUserId($attributes['user_id'])->pluck('id')
                );
            },
        ];
    }
}
