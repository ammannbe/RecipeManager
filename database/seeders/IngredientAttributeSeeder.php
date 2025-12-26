<?php

namespace Database\Seeders;

use App\Models\IngredientAttribute;
use Illuminate\Database\Seeder;

class IngredientAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        IngredientAttribute::factory(50)->create();
    }
}
