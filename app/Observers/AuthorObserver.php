<?php

namespace App\Observers;

use App\Models\Author;

class AuthorObserver
{
    /**
     * Handle the author "saving" event.
     *
     * @param  \App\Models\Author  $author
     * @return void
     */
    public function saving(Author $author)
    {
        $author->slugifyName();
    }
}
