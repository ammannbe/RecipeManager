<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('rating_criteria');
    }

    /**
     * Recreates both tables in their final shape (no slug, no user_id) without the lost rows.
     */
    public function down(): void
    {
        Schema::create('rating_criteria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 20)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedBigInteger('rating_criterion_id');
            $table->foreignId('author_id')->nullable()->constrained();
            $table->text('comment');
            $table->unsignedTinyInteger('stars')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('rating_criterion_id')->references('id')->on('rating_criteria');
        });
    }
};
