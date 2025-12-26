<?php

namespace App\Observers;

use App\Models\Recipe;
use App\Models\User;

class UserObserver
{
    public function deleting(User $user): void
    {
        $user->recipes()->each(fn (Recipe $r) => $r->delete());
    }
}
