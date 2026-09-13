<?php

namespace App\Policies;

use App\Models\Cookbook;
use App\Models\User;

class CookbookPolicy
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

    public function view(User $user, Cookbook $cookbook): bool
    {
        return $cookbook->grants($user, 'can_read');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Cookbook $cookbook): bool
    {
        return $this->share($user, $cookbook);
    }

    public function delete(User $user, Cookbook $cookbook): bool
    {
        return $this->share($user, $cookbook);
    }

    public function restore(User $user, Cookbook $cookbook): bool
    {
        return $this->share($user, $cookbook);
    }

    public function forceDelete(User $user, Cookbook $cookbook): bool
    {
        return false;
    }

    /**
     * Renaming, publishing, deleting and managing members of the cookbook itself.
     */
    public function share(User $user, Cookbook $cookbook): bool
    {
        return $cookbook->isOwnedBy($user) || $cookbook->grants($user, 'can_admin');
    }
}
