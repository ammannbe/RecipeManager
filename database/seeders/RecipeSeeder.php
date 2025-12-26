<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class RecipeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /** @var Collection<int, Tag> $tags */
        $tags = Tag::get();

        Recipe::factory(100)
            ->recycle(User::get())
            ->recycle(Category::get())
            ->create()
            ->each(function (Recipe $recipe) use ($tags) {
                $recipe->tags()->attach($tags->random(rand(0, 3)));
            });
    }
}
