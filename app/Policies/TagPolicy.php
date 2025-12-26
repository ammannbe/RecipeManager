<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
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

    public function view(User $user, Tag $tag): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Tag $tag): bool
    {
        return false;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->admin && $tag->recipes->isEmpty();
    }

    public function restore(User $user, Tag $tag): bool
    {
        return false;
    }

    public function forceDelete(User $user, Tag $tag): bool
    {
        return $user->admin && $tag->recipes->isEmpty();
    }
}
