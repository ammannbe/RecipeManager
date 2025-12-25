<?php

namespace Database\Factories\Recipes;

use App\Models\User;
use App\Models\Recipes\Recipe;
use App\Models\Recipes\Category;
use App\Models\Recipes\Cookbook;
use App\Models\AdminOrOwnerScope;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Recipe::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $cookbook = $this->faker->randomElement([null, Cookbook::withoutGlobalScope(AdminOrOwnerScope::class)->inRandomOrder()->first()]);

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
