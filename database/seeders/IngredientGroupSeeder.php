<?php

namespace Database\Seeders;

use App\Models\IngredientGroup;
use App\Models\Recipe;
use Illuminate\Database\Seeder;

class IngredientGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        IngredientGroup::factory(50)->recycle(Recipe::get())->create();
    }
}
