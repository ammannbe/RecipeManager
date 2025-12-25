<?php

namespace App\Observers\Recipes;

use App\Models\Recipes\Category;

class CategoryObserver
{
    /**
     * Handle the category "saving" event.
     *
     * @param  \App\Models\Recipes\Category  $category
     * @return void
     */
    public function saving(Category $category)
    {
        $category->slugifyName();
    }
}
