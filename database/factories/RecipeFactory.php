<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
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
            'complexity' => $this->faker->randomElement(Recipe::COMPLEXITY_TYPES),
            'instructions' => $this->faker->unique(reset: true)->text(),
            'preparation_time' => $this->faker->optional()->time('H:i:00', '23:59'),
        ];
    }
}
