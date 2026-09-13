<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A soft-deleted cookbook still occupied the unique index, so its name could never
 * be used again. MariaDB treats NULL as distinct, so including deleted_at frees the
 * name as soon as the row is trashed while still blocking live duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The author_id foreign key leans on the old index, so add the replacement first.
        Schema::table('cookbooks', function (Blueprint $table) {
            $table->unique(['author_id', 'name', 'deleted_at']);
        });

        Schema::table('cookbooks', function (Blueprint $table) {
            $table->dropUnique(['author_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('cookbooks', function (Blueprint $table) {
            $table->unique(['author_id', 'name']);
        });

        Schema::table('cookbooks', function (Blueprint $table) {
            $table->dropUnique(['author_id', 'name', 'deleted_at']);
        });
    }
};
