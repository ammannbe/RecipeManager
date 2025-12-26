<?php

namespace App\Observers;

use App\Models\Category;

class CategoryObserver
{
    /**
     * Handle the category "saving" event.
     *
     * @param  \App\Models\Category  $category
     * @return void
     */
    public function saving(Category $category)
    {
        $category->slugifyName();
    }
}
