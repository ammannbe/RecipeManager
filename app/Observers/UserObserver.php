<?php

namespace App\Observers;

use App\Models\Cookbook;
use App\Models\User;
use App\Services\Households;
use LogicException;

class UserObserver
{
    /**
     * A linked account signs in through the OIDC provider only; link and unlink through App\Auth\OidcAccount.
     */
    public function saving(User $user): void
    {
        if ($user->isLinkedToOidc() && $user->password !== null && $user->isDirty(['password', 'oidc_sub'])) {
            throw new LogicException('An account linked to the OIDC sign-in cannot have a password.');
        }
    }

    public function deleting(User $user): void
    {
        $user->author?->cookbooks()->each(fn (Cookbook $cookbook) => $cookbook->delete());

        $user->sharedCookbooks()->detach();

        app(Households::class)->forget($user);
    }
}
