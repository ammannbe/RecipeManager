<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('recipes')->get()->each(function ($recipe) {
            $html = Str::markdown($recipe->instructions ?? '');

            DB::table('recipes')
                ->where('id', $recipe->id)
                ->update(['instructions' => $html]);
        });
    }
};
