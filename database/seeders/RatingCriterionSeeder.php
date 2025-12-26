<?php

namespace Database\Seeders;

use App\Models\RatingCriterion;
use Illuminate\Database\Seeder;

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
