<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;
use App\Services\Households;

/**
 * Households are private to their members, also for global admins. Managing them is
 * only possible with the local driver; with "konto" they are read-only mirrors.
 */
class HouseholdPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Household $household): bool
    {
        return $household->hasMember($user);
    }

    public function create(User $user): bool
    {
        return Households::isLocal();
    }

    /**
     * Renaming.
     */
    public function update(User $user, Household $household): bool
    {
        return $this->manage($user, $household);
    }

    public function delete(User $user, Household $household): bool
    {
        return $this->manage($user, $household);
    }

    /**
     * Inviting, removing members and changing roles.
     */
    public function manageMembers(User $user, Household $household): bool
    {
        return $this->manage($user, $household);
    }

    public function leave(User $user, Household $household): bool
    {
        return Households::isLocal() && $household->hasMember($user);
    }

    private function manage(User $user, Household $household): bool
    {
        return Households::isLocal() && $household->isAdministeredBy($user);
    }
}
