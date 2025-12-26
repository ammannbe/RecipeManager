<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['delete', 'forceDelete'])) {
            return null;
        }

        if ($user->admin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Unit $unit): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Unit $unit): bool
    {
        return false;
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->admin && $unit->ingredients->isEmpty();
    }

    public function restore(User $user, Unit $unit): bool
    {
        return false;
    }

    public function forceDelete(User $user, Unit $unit): bool
    {
        return $user->admin && $unit->ingredients->isEmpty();
    }
}
