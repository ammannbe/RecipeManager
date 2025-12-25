<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCookbooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cookbooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 20);
            $table->string('slug', 40);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('author_id');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'user_id']);
            $table->unique(['slug', 'user_id']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('authors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cookbooks');
    }
}
