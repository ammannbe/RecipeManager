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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('author_id')
                ->after('id')
                ->nullable();
        });

        DB::statement('
            UPDATE users
            INNER JOIN authors
                ON authors.user_id = users.id
            SET users.author_id = authors.id
        ');

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('author_id')
                ->nullable(false)
                ->change();

            $table->foreign('author_id')
                ->references('id')
                ->on('authors')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
        });
    }
};
