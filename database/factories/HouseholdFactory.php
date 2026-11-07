<?php

namespace Database\Factories;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Familie '.$this->faker->lastName(),
        ];
    }

    /**
     * Adds the user as admin (and creator) of the household.
     */
    public function administeredBy(User $user): static
    {
        return $this->state(fn (): array => ['created_by' => $user->getKey()])
            ->afterCreating(fn (Household $household) => $household->users()->attach($user->getKey(), [
                'role' => HouseholdRole::Admin->value,
                'joined_at' => now(),
            ]));
    }

    public function withMember(User $user, HouseholdRole $role = HouseholdRole::Member): static
    {
        return $this->afterCreating(fn (Household $household) => $household->users()->attach($user->getKey(), [
            'role' => $role->value,
            'joined_at' => now(),
        ]));
    }
}
