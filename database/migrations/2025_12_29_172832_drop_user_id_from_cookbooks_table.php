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
        Schema::table('cookbooks', function (Blueprint $table) {
            $table->dropUnique(['name', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cookbooks', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->after('name')->nullable();
        });

        DB::statement('
            UPDATE cookbooks
            INNER JOIN users
                ON users.author_id = cookbooks.author_id
            SET cookbooks.user_id = users.id
        ');

        Schema::table('cookbooks', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['name', 'user_id']);
        });
    }
};
