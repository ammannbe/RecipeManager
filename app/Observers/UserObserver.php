<?php

namespace App\Observers;

use App\Models\Cookbook;
use App\Models\User;
use App\Services\Households;

class UserObserver
{
    public function deleting(User $user): void
    {
        $user->author?->cookbooks()->each(fn (Cookbook $cookbook) => $cookbook->delete());

        $user->sharedCookbooks()->detach();

        app(Households::class)->forget($user);
    }
}
