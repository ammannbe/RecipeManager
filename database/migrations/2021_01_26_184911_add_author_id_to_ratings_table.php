<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAuthorIdToRatingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->after('user_id')->constrained();
        });

        // Raw SQL: the Rating model was removed when the feature was dropped.
        DB::statement('
            UPDATE ratings
            INNER JOIN users ON users.id = ratings.user_id
            INNER JOIN authors ON authors.user_id = users.id
            SET ratings.author_id = authors.id
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropColumn('author_id');
        });
    }
}
