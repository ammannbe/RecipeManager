<?php

namespace Database\Seeders;

use App\Models\Recipe;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $tags = Tag::get();
        Recipe::factory()->times(100)->create()->each(function ($recipe) use ($tags) {
            $random = $tags->random(rand(0, 3))->pluck('id');
            $recipe->tags()->attach($random);
        });
    }
}
