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
        Schema::table('recipes', function (Blueprint $table) {
            $table->json('photos')->nullable()->default(null)->after('instructions');
        });

        DB::table('media')->get()->each(function ($media) {
            $fileName = $media->uuid.'.'.Str::of($media->mime_type)->after('/');

            $recipe = DB::table('recipes')->find($media->model_id);

            if (! file_exists(storage_path('app/public/recipes/'.$media->model_id))) {
                mkdir(storage_path('app/public/recipes/'.$media->model_id));
            }

            rename(
                storage_path('app/images/recipes/'.$media->id.'/'.$media->file_name),
                storage_path('app/public/recipes/'.$recipe->id.'/'.$fileName) // @phpstan-ignore-line
            );

            $photos = json_decode($recipe->photos ?: '[]'); // @phpstan-ignore-line

            $photos[] = $fileName;

            DB::table('recipes')
                ->where('id', $recipe->id) // @phpstan-ignore-line
                ->update(['photos' => $photos]);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropIfExists();
        });
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

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};
