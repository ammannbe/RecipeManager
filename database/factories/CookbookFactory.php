<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CookbookFactory extends Factory
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
            'user_id' => User::factory(),
            'author_id' => function (array $attributes) {
                return $this->faker->randomElement(
                    Author::whereUserId($attributes['user_id'])->pluck('id')
                );
            },
        ];
    }
}
