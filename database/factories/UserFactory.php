<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique(reset: true)->safeEmail,
            'email_verified_at' => now(),
            'password' => \Hash::make('password'),
            'remember_token' => \Str::random(10),
        ];
    }
}
