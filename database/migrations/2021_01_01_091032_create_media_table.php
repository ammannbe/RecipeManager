<?php

use App\Models\Recipes\Recipe;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMediaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
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

        $this->migratePhotos();

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }

    /**
     * Migrate existing photos to new database schema
     *
     * @return void
     */
    private function migratePhotos(): void
    {
        if (empty(\Storage::disk('recipe_photos')->directories())) {
            return;
        }

        $path = storage_path('app/images/recipes');
        $pathOld = storage_path('app/images/recipes.old');

        \File::moveDirectory($path, $pathOld);
        \Storage::disk('recipe_photos')->makeDirectory('');
        if (\File::exists("{$pathOld}/.gitignore")) {
            \File::copy("{$pathOld}/.gitignore", "{$path}/.gitignore");
        }
        /** @var \App\Models\Recipes\Recipe[] */
        $recipes = Recipe::withoutGlobalScopes()->whereNotNull('photos')->get();
        foreach ($recipes as $recipe) {
            if (!\File::exists("{$pathOld}/{$recipe->id}")) {
                continue;
            }

            $files = \File::files("{$pathOld}/{$recipe->id}");

            foreach ($files as $file) {
                $recipe->addMedia($file->getPathname())->toMediaCollection('recipe_photos');
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('media');

        Schema::table('recipes', function (Blueprint $table) {
            $table->json('photos')->nullable()->default(null)->after('insructions');
        });
    }
}
