<?php

namespace Database\Factories;

use App\Models\Author;
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
            'author_id' => Author::factory(),
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password' => \Hash::make('password'),
            'remember_token' => \Str::random(10),
        ];
    }

    /**
     * Like the app: an account linked to the OIDC sign-in has no password.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            if ($user->isLinkedToOidc()) {
                $user->password = null;
            }
        });
    }

    public function linked(string $sub = 'sub-123'): static
    {
        return $this->state(fn (): array => ['oidc_sub' => $sub]);
    }

    public function admin(): static
    {
        return $this->state(fn (): array => ['admin' => true]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }
}
