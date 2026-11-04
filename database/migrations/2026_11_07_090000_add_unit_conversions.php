<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unit converter: units measure mass or volume (base amount in g or ml), foods have a density
 * and the measure their amounts are converted to, piece units (Stück, Zehe, Bund, …) weigh per food.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('measure', 10)->nullable();
            $table->decimal('base_amount', 10, 4)->nullable()->comment('g or ml per unit');
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->string('measure', 10)->nullable()->comment('converted to');
            $table->decimal('density', 6, 3)->nullable()->comment('g per ml');
        });

        Schema::create('food_unit_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->decimal('grams', 10, 2);
            $table->timestamps();
            $table->unique(['food_id', 'unit_id']);
        });

        // US cup next to the Swiss "Tasse" (2 dl), only where the standard units exist (not in
        // empty test databases); the command assigns its 240 ml.
        if (DB::table('units')->where('name', 'Tasse')->exists() && ! DB::table('units')->where('name', 'Cup')->exists()) {
            DB::table('units')->insert([
                'name' => 'Cup',
                'name_shortcut' => 'Cup',
                'name_plural' => 'Cups',
                'name_plural_shortcut' => 'Cups',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('food_unit_weights');

        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn(['measure', 'density']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['measure', 'base_amount']);
        });
    }
};
