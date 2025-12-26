<?php

namespace App\Observers;

use App\Models\Tag;

class TagObserver
{
    /**
     * Handle the tag "saving" event.
     *
     * @param  \App\Models\Tag  $tag
     * @return void
     */
    public function saving(Tag $tag)
    {
        $tag->slugifyName();
    }
}
