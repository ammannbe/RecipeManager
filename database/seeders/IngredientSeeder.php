<?php

namespace Database\Seeders;

use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientAttribute;
use App\Models\Recipe;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class IngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /** @var Collection<int, IngredientAttribute> */
        $attributes = IngredientAttribute::get();

        Ingredient::factory(500)
            ->recycle(Recipe::get())
            ->recycle(Unit::get())
            ->recycle(Food::get())
            ->create()
            ->each(function (Ingredient $ingredient) use ($attributes) {
                $ingredient->ingredientAttributes()->attach($attributes->random(rand(0, 3)));
            });
    }
}
