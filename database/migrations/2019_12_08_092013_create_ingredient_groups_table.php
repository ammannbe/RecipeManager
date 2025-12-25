<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIngredientGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ingredient_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            $table->string('name', 20);
            $table->string('slug', 40);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'recipe_id']);
            $table->unique(['slug', 'recipe_id']);

            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ingredient_groups');
    }
}
