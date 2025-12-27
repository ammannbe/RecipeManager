<?php

namespace Database\Factories;

use App\Enums\Complexity;
use App\Models\Author;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'author_id' => function (array $attributes) {
                return $this->faker->randomElement(
                    Author::whereUserId($attributes['user_id'])->pluck('id')
                );
            },
            'cookbook_id' => function (array $attributes) {
                return $this->faker->optional()->randomElement(
                    Cookbook::whereUserId($attributes['user_id'])->pluck('id')
                );
            },
            'category_id' => Category::factory(),
            'name' => $this->faker->unique(reset: true)->word(),
            'servings' => $this->faker->optional()->numberBetween(1, 20),
            'serving_type' => $this->faker->optional()->word(),
            'complexity' => $this->faker->randomElement(Complexity::cases()),
            'instructions' => $this->faker->unique(reset: true)->randomHtml(),
            'preparation_time' => $this->faker->optional()->time('H:i:00', '23:59'),
        ];
    }
}
