<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The photos column used to store plain filename strings. It now stores
 * {path, source, is_ai_generated} objects so each image can carry metadata.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('recipes')->whereNotNull('photos')->orderBy('id')->each(function (object $recipe): void {
            $photos = json_decode($recipe->photos, true) ?? [];

            $photos = array_map(function ($photo) {
                if (is_array($photo)) {
                    return $photo;
                }

                return ['path' => $photo, 'source' => null, 'is_ai_generated' => false];
            }, $photos);

            DB::table('recipes')->where('id', $recipe->id)->update(['photos' => json_encode($photos)]);
        });
    }

    public function down(): void
    {
        DB::table('recipes')->whereNotNull('photos')->orderBy('id')->each(function (object $recipe): void {
            $photos = json_decode($recipe->photos, true) ?? [];

            $photos = array_map(function ($photo) {
                return is_array($photo) ? $photo['path'] : $photo;
            }, $photos);

            DB::table('recipes')->where('id', $recipe->id)->update(['photos' => json_encode($photos)]);
        });
    }
};
