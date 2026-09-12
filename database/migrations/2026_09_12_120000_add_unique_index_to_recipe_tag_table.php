<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The table has no primary key, so collapse duplicates by rebuilding it.
        DB::statement('CREATE TEMPORARY TABLE recipe_tag_unique AS SELECT DISTINCT recipe_id, tag_id FROM recipe_tag');
        DB::statement('DELETE FROM recipe_tag');
        DB::statement('INSERT INTO recipe_tag (recipe_id, tag_id) SELECT recipe_id, tag_id FROM recipe_tag_unique');
        DB::statement('DROP TEMPORARY TABLE recipe_tag_unique');

        Schema::table('recipe_tag', function (Blueprint $table) {
            $table->unique(['recipe_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::table('recipe_tag', function (Blueprint $table) {
            $table->dropUnique(['recipe_id', 'tag_id']);
        });
    }
};
