<?php

namespace App\Observers;

use App\Models\Cookbook;

class CookbookObserver
{
    /**
     * Handle the cookbook "creating" event.
     *
     * @param  \App\Models\Cookbook  $cookbook
     * @return void
     */
    public function creating(Cookbook $cookbook)
    {
        if (!$cookbook->author_id) {
            $cookbook->author_id = auth()->user()->author->id;
        }
    }

    /**
     * Handle the cookbook "saving" event.
     *
     * @param  \App\Models\Cookbook  $cookbook
     * @return void
     */
    public function saving(Cookbook $cookbook)
    {
        $cookbook->slugifyName();
    }
}
