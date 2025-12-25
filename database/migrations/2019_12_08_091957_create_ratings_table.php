<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRatingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('rating_criterion_id');
            $table->text('comment');
            $table->unsignedTinyInteger('stars')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rating_criterion_id')->references('id')->on('rating_criteria');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ratings');
    }
}
