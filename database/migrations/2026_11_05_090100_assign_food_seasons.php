<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Fills the seasonal calendar once from database/data/food-seasons.php. Foods that already
 * have months keep them; rerun `foods:seasons` later to pick up new foods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('foods:seasons');
    }

    public function down(): void
    {
        // The months stay; the column migration drops them.
    }
};
