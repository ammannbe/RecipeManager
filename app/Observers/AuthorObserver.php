<?php

namespace App\Observers;

use App\Households\AccountManagerUsers;
use App\Models\Author;
use App\Models\Recipe;

class AuthorObserver
{
    /**
     * The author name is the user's name in the AccountManager (ACCOUNT_MANAGER_REPORT_USERS).
     */
    public function updated(Author $author): void
    {
        if ($author->wasChanged('name') && AccountManagerUsers::enabled()) {
            $userId = $author->user()->value('id');

            if ($userId !== null) {
                AccountManagerUsers::report((int) $userId);
            }
        }
    }

    public function deleting(Author $author): void
    {
        $author->recipes()->each(fn (Recipe $r) => $r->delete());
    }
}
