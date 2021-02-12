<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Recipes\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Category::factory()->times(15)->create();
    }
}
