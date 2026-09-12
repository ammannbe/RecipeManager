<?php

namespace Database\Factories;

use App\Enums\Complexity;
use App\Models\Author;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Recipe;
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
            'author_id' => Author::factory(),
            'cookbook_id' => function (array $attributes) {
                return $this->faker->optional()->randomElement(
                    Cookbook::whereAuthorId($attributes['author_id'])->pluck('id')
                );
            },
            'category_id' => Category::factory(),
            // Mirrors the pre-is_public rule: a recipe outside a cookbook was public.
            'is_public' => fn (array $attributes): bool => $attributes['cookbook_id'] === null,
            // Faker's word pool is far smaller than the number of seeded recipes.
            'name' => $this->faker->unique()->words(asText: true),
            'servings' => $this->faker->optional()->numberBetween(1, 20),
            'serving_type' => $this->faker->optional()->word(),
            'complexity' => $this->faker->randomElement(Complexity::cases()),
            'instructions' => $this->faker->randomHtml(),
            'preparation_time' => $this->faker->optional()->time('H:i:00', '23:59'),
        ];
    }
}
