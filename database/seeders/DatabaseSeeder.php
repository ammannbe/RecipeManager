<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            UserSeeder::class,

            CategorySeeder::class,
            FoodSeeder::class,
            IngredientAttributeSeeder::class,
            RatingCriterionSeeder::class,
            TagSeeder::class,
            UnitSeeder::class,

            CookbookSeeder::class,
            RecipeSeeder::class,
            IngredientGroupSeeder::class,
            IngredientSeeder::class,
            RatingSeeder::class,
        ]);
    }
}
