<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Fills empty unit converter fields (measure, density, piece weights) from the extended
 * database/data/food-conversions.php. Each empty field is filled on its own; values already
 * set are kept.
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
