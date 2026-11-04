<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Fills the unit converter data once from database/data/food-conversions.php. Values set by hand
 * are kept; rerun `foods:conversions` later to pick up new foods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('foods:conversions');
    }

    public function down(): void
    {
        // The values stay; the column migration drops them.
    }
};
