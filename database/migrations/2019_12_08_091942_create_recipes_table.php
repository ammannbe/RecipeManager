<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecipesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cookbook_id')->nullable()->default(null);
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('author_id');
            $table->string('name', 100);
            $table->string('slug', 200);
            $table->decimal('yield_amount', 3, 0)->nullable()->default(4);
            $table->enum('complexity', ['simple', 'normal', 'difficult'])->default('normal');
            $table->mediumtext('instructions');
            $table->json('photos')->nullable()->default(null);
            $table->time('preparation_time')->nullable()->default(null);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'cookbook_id']);
            $table->unique(['slug', 'cookbook_id']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cookbook_id')->references('id')->on('cookbooks')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories');
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
        Schema::dropIfExists('recipes');
    }
}
