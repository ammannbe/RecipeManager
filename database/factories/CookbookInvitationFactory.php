<?php

namespace Database\Factories;

use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CookbookInvitation>
 */
class CookbookInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cookbook_id' => Cookbook::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'can_read' => true,
            'token_hash' => CookbookInvitation::hash(CookbookInvitation::newToken()),
            'expires_at' => now()->addDays(14),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => ['accepted_at' => now()]);
    }
}
