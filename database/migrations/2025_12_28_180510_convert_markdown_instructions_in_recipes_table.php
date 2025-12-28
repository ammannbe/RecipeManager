<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \DB::table('recipes')->get()->each(function ($recipe) {
            $html = app(\Spatie\LaravelMarkdown\MarkdownRenderer::class) // @phpstan-ignore-line
                ->toHtml($recipe->instructions);

            \DB::table('recipes')
                ->where('id', $recipe->id)
                ->update(['instructions' => $html]);
        });
    }
};
