<?php

namespace Database\Factories;

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
        $cookbook = $this->faker->randomElement([null, Cookbook::inRandomOrder()->first()]);

        $user = User::inRandomOrder()->first();
        if ($cookbook) {
            /** @var \App\Models\User $user */
            $user = User::find($cookbook->user_id);
        }

        return [
            'user_id' => $user->id,
            'cookbook_id' => $cookbook->id ?? null,
            'category_id' => Category::inRandomOrder()->first()->id,
            'author_id' => $user->author->id,
            'name' => $this->faker->unique(true)->name,
            'servings' => $this->faker->randomElement([null, $this->faker->numberBetween(0, 30)]),
            'complexity' => $this->faker->randomElement(Recipe::COMPLEXITY_TYPES),
            'instructions' => $this->faker->unique(true)->text,
            'preparation_time' => $this->faker->randomElement([null, $this->faker->time('H:i:00', '24:59')]),
        ];
    }
}
