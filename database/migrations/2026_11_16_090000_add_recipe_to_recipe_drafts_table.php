<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drafts remember the recipe imported from them, so the companion app (Shoppinglist) can link
 * its entry to the real recipe (GET /api/recipe-drafts/{draft}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_drafts', function (Blueprint $table) {
            $table->foreignId('recipe_id')->nullable()->after('data')->constrained()->nullOnDelete();
            // Stays set when the recipe is deleted later (recipe_id becomes null).
            $table->timestamp('imported_at')->nullable()->after('recipe_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('recipe_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipe_id');
            $table->dropIndex(['imported_at']);
            $table->dropColumn('imported_at');
        });
    }
};
