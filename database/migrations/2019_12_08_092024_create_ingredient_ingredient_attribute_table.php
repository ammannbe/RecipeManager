<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIngredientIngredientAttributeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ingredient_ingredient_attribute', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id');
            $table->unsignedBigInteger('ingredient_attribute_id');

            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
            $table->foreign('ingredient_attribute_id')->references('id')->on('ingredient_attributes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ingredient_ingredient_attribute');
    }
}
