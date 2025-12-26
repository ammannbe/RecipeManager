<?php

namespace App\Policies;

use App\Models\RatingCriterion;
use App\Models\User;

class RatingCriterionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->admin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RatingCriterion $criterion): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RatingCriterion $criterion): bool
    {
        return false;
    }

    public function delete(User $user, RatingCriterion $criterion): bool
    {
        return $criterion->ratings->isEmpty();
    }

    public function restore(User $user, RatingCriterion $criterion): bool
    {
        return false;
    }

    public function forceDelete(User $user, RatingCriterion $criterion): bool
    {
        return $criterion->ratings->isEmpty();
    }
}
