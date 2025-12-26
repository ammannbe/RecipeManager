<?php

namespace Database\Seeders;

use App\Models\Cookbook;
use App\Models\User;
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
        Cookbook::factory(20)->recycle(User::get())->create();
    }
}
