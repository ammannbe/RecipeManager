<?php

namespace Database\Seeders;

use App\Models\IngredientGroup;
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
        IngredientGroup::factory()->times(40)->create();
    }
}
