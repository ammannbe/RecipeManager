<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\LaravelMarkdown\MarkdownRenderer;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('recipes')->get()->each(function ($recipe) {
            $html = app(MarkdownRenderer::class) // @phpstan-ignore-line
                ->toHtml($recipe->instructions);

            DB::table('recipes')
                ->where('id', $recipe->id)
                ->update(['instructions' => $html]);
        });
    }
};
