<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cookbooks', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('name')->index();

            // The form already allowed 100 characters while the column held 20.
            $table->string('name', 100)->change();

            // The per-owner uniqueness was dropped with user_id and never replaced.
            $table->unique(['author_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('cookbooks', function (Blueprint $table) {
            $table->dropUnique(['author_id', 'name']);
            $table->dropIndex(['is_public']);
            $table->dropColumn('is_public');
            $table->string('name', 20)->change();
        });
    }
};
