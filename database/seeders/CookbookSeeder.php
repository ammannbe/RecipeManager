<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Cookbook;
use Illuminate\Database\Seeder;

class CookbookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Cookbook::factory(20)->recycle(Author::get())->create();
    }
}
