<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::table('cookbooks', function (Blueprint $table) {
            $table->dropUnique(['slug', 'user_id']);
            $table->dropColumn('slug');
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::table('ingredient_attributes', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::table('ingredient_groups', function (Blueprint $table) {
            $table->dropUnique(['slug', 'recipe_id']);
            $table->dropColumn('slug');
        });

        Schema::table('rating_criteria', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropUnique(['slug', 'cookbook_id']);
            $table->dropColumn('slug');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->string('slug', 100)->unique();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug', 100)->unique();
        });

        Schema::table('cookbooks', function (Blueprint $table) {
            $table->string('slug', 40)->unique();
            $table->unique(['slug', 'user_id']);
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->string('slug', 100)->unique();
        });

        Schema::table('ingredient_attributes', function (Blueprint $table) {
            $table->string('slug', 80)->unique();
        });

        Schema::table('ingredient_groups', function (Blueprint $table) {
            $table->string('slug', 40)->unique();
            $table->unique(['slug', 'recipe_id']);
        });

        Schema::table('rating_criteria', function (Blueprint $table) {
            $table->string('slug', 40)->unique();
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->string('slug', 200)->unique();
            $table->unique(['slug', 'cookbook_id']);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->string('slug', 40)->unique();
        });

        Schema::table('units', function (Blueprint $table) {
            $table->string('slug', 40)->unique();
        });
    }
};
