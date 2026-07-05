<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredient_groups', function (Blueprint $table): void {
            $table->unsignedInteger('position')->nullable()->after('name');
            $table->unique(['recipe_id', 'position'], 'ingredient_groups_recipe_position_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_groups', function (Blueprint $table): void {
            $table->dropUnique('ingredient_groups_recipe_position_unique');
            $table->dropColumn('position');
        });
    }
};
