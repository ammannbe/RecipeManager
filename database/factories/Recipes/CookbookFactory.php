<?php

namespace Database\Factories\Recipes;

use App\Models\User;
use App\Models\Recipes\Cookbook;
use Illuminate\Database\Eloquent\Factories\Factory;

class CookbookFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Cookbook::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $user = User::inRandomOrder()->first();

        return [
            'name' => $this->faker->unique(true)->word,
            'user_id' => $user->id,
            'author_id' => $user->author->id,
        ];
    }
}
