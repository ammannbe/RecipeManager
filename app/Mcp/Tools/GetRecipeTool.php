<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Models\Recipe;
use App\Services\Api\RecipePresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-recipe')]
#[Description('Returns a recipe the user can see: ingredients (optionally scaled to other servings, with amounts converted to g/ml where known), preparation steps, category, cookbook, tags and URL.')]
#[IsReadOnly]
class GetRecipeTool extends RecipeTool
{
    public function __construct(private RecipePresenter $presenter) {}

    protected function permission(): McpPermission
    {
        return McpPermission::Read;
    }

    public function handle(Request $request): Response
    {
        $user = $this->user($request);
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'servings' => ['nullable', 'numeric', 'min:0.25', 'max:999'],
        ]);

        /** @var Recipe|null $recipe */
        $recipe = Recipe::query()->with(['category', 'cookbook', 'tags', 'author'])->find((int) $data['id']);

        if ($recipe === null || ! $user->can('view', $recipe)) {
            return Response::error('No recipe with this id that you can see.');
        }

        return Response::json([
            ...$this->presenter->detail($recipe, isset($data['servings']) ? (float) $data['servings'] : null, $user->locale ?? app()->getLocale()),
            'category' => $recipe->category?->name,
            'cookbook' => $recipe->cookbook?->name,
            'author' => $recipe->author?->name,
            'tags' => $recipe->tags->pluck('name')->all(),
            'complexity' => $recipe->complexity->value,
            'preparation_time' => $recipe->preparation_time?->format('H:i'),
            'is_public' => $recipe->is_public,
            'source' => $recipe->source,
            'editable' => $user->can('update', $recipe),
            'instructions' => trim(html_entity_decode(strip_tags(str_replace(['</li>', '</p>', '<br>'], "\n", (string) $recipe->instructions)))),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The recipe id from search-recipes.')->required(),
            'servings' => $schema->number()->description('Scale the amounts to this many servings (only recipes with servings).'),
        ];
    }
}
