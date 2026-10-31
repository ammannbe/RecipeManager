<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Swiss seasonal calendar: months of fresh harvest and months available from storage only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->json('season_months')->nullable();
            $table->json('storage_months')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn(['season_months', 'storage_months']);
        });
    }
};
