<?php

namespace App\Observers;

use App\Models\Cookbook;
use App\Models\Recipe;

class CookbookObserver
{
    public function deleting(Cookbook $cookbook): void
    {
        $cookbook->recipes()->each(fn (Recipe $r) => $r->delete());
    }
}
