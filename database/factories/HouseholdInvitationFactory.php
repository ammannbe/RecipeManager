<?php

namespace Database\Factories;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HouseholdInvitation>
 */
class HouseholdInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'role' => HouseholdRole::Member->value,
            'token_hash' => HouseholdInvitation::hash(HouseholdInvitation::newToken()),
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
