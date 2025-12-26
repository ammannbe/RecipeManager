<?php

namespace Database\Seeders;

use App\Models\Rating;
use App\Models\RatingCriterion;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;

class RatingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Rating::factory(50)
            ->recycle(Recipe::get())
            ->recycle(User::get())
            ->recycle(RatingCriterion::get())
            ->create();
    }
}
