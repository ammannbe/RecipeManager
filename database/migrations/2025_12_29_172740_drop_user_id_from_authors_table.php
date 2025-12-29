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
            $table->dropConstrainedForeignId('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->after('id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
        });

        \DB::statement('
            UPDATE authors
            INNER JOIN users
                ON users.author_id = authors.id
            SET authors.user_id = users.id
        ');
    }
};
