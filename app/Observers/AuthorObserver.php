<?php

namespace App\Observers;

use App\Models\Author;
use App\Models\Recipe;

class AuthorObserver
{
    public function deleting(Author $author): void
    {
        $author->recipes()->each(fn (Recipe $r) => $r->delete());
    }
}
