<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IngredientGroup;

class IngredientGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        IngredientGroup::factory()->times(40)->create();
    }
}
