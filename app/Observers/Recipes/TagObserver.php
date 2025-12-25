<?php

namespace App\Observers\Recipes;

use App\Models\Recipes\Tag;

class TagObserver
{
    /**
     * Handle the tag "saving" event.
     *
     * @param  \App\Models\Recipes\Tag  $tag
     * @return void
     */
    public function saving(Tag $tag)
    {
        $tag->slugifyName();
    }
}
