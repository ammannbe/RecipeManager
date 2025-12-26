<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RatingCriterion;

class RatingCriterionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        RatingCriterion::factory()->times(50)->create();
    }
}
