<?php

namespace App\Observers\Users;

use App\Models\Users\Author;

class AuthorObserver
{
    /**
     * Handle the author "saving" event.
     *
     * @param  \App\Models\Recipes\Author  $author
     * @return void
     */
    public function saving(Author $author)
    {
        $author->slugifyName();
    }
}
