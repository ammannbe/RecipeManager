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
        Schema::table('media', function (Blueprint $table) {
            $table->dropIfExists();
        });

        if (file_exists(storage_path('app/public/recipes/.gitignore'))) {
            unlink(storage_path('app/public/recipes/.gitignore'));
        }

        rename(storage_path('app/images/recipes'), storage_path('app/public/recipes'));

        if (file_exists('app/images/.gitignore')) {
            unlink(storage_path('app/images/.gitignore'));
        }

        if (file_exists(storage_path('app/images'))) {
            rmdir(storage_path('app/images'));
        }

        foreach (\Storage::disk('recipes')->directories() as $directory) {
            \Storage::disk('recipes')->deleteDirectory($directory . '/conversions');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->morphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable();

            $table->nullableTimestamps();
        });
    }
};
