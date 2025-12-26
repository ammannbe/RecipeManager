<?php

namespace Database\Factories;

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
        $user = User::inRandomOrder()->first();

        return [
            'name' => $this->faker->unique(true)->word,
            'user_id' => $user->id,
            'author_id' => $user->author->id,
        ];
    }
}
