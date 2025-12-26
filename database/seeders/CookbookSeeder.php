<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cookbook;

class CookbookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Cookbook::factory()->times(20)->create();
    }
}
