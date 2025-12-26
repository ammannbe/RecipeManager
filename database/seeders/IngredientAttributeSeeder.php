<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IngredientAttribute;

class IngredientAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        IngredientAttribute::factory()->times(30)->create();
    }
}
