<?php

namespace App\Policies;

use App\Models\Rating;
use App\Models\User;

class RatingPolicy
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

    public function view(User $user, Rating $rating): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Rating $rating): bool
    {
        return $user->author_id === $rating->author_id;
    }

    public function delete(User $user, Rating $rating): bool
    {
        return $user->author_id === $rating->author_id;
    }

    public function restore(User $user, Rating $rating): bool
    {
        return $user->author_id === $rating->author_id;
    }

    public function forceDelete(User $user, Rating $rating): bool
    {
        return $user->author_id === $rating->author_id;
    }
}
