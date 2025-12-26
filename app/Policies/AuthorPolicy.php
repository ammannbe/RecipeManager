<?php

namespace App\Policies;

use App\Models\Author;
use App\Models\User;

class AuthorPolicy
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

    public function view(User $user, Author $author): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Author $author): bool
    {
        return $user->author->id === $author->id;
    }

    public function delete(User $user, Author $author): bool
    {
        return $user->author->id === $author->id;
    }

    public function restore(User $user, Author $author): bool
    {
        return false;
    }

    public function forceDelete(User $user, Author $author): bool
    {
        return $user->author->id === $author->id;
    }
}
