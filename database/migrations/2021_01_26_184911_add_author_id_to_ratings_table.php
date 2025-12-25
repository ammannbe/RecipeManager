<?php

use App\Models\Ratings\Rating;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        Rating::each(function ($rating) {
            $rating->author_id = $rating->user->author->id ?? null;
            $rating->update();
        });
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
