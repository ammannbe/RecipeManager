<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Models\Recipe;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-recipes')]
#[Description('Searches the recipes the user can see by name, ingredient, category, cookbook or tag. Returns id, name, category, cookbook, servings and URL; use get-recipe for ingredients and preparation.')]
#[IsReadOnly]
class SearchRecipesTool extends RecipeTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::Read;
    }

    public function handle(Request $request): Response
    {
        $user = $this->user($request);
        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'cookbook' => ['nullable', 'string', 'max:50'],
            'tag' => ['nullable', 'string', 'max:50'],
            'only_mine' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = trim((string) ($data['query'] ?? ''));

        $recipes = Recipe::query()
            ->visibleTo($user)
            ->with(['category', 'cookbook'])
            ->when($query !== '', fn (Builder $builder) => $builder->where(fn (Builder $builder) => $builder
                ->where('name', 'like', '%'.$query.'%')
                ->orWhereHas('ingredients.food', fn (Builder $food) => $food->where('name', 'like', '%'.$query.'%'))))
            ->when(filled($data['category'] ?? null), fn (Builder $builder) => $builder->whereHas('category', fn (Builder $category) => $category->where('name', $data['category'])))
            ->when(filled($data['cookbook'] ?? null), fn (Builder $builder) => $builder->whereHas('cookbook', fn (Builder $cookbook) => $cookbook->where('name', $data['cookbook'])))
            ->when(filled($data['tag'] ?? null), fn (Builder $builder) => $builder->whereHas('tags', fn (Builder $tag) => $tag->where('name', $data['tag'])))
            ->when((bool) ($data['only_mine'] ?? false), fn (Builder $builder) => $builder->where('author_id', $user->author_id))
            ->orderBy('name')
            ->limit((int) ($data['limit'] ?? 20))
            ->get();

        return Response::json($recipes->map(fn (Recipe $recipe): array => [
            'id' => $recipe->id,
            'name' => $recipe->name,
            'category' => $recipe->category?->name,
            'cookbook' => $recipe->cookbook?->name,
            'servings' => $recipe->servings,
            'serving_type' => $recipe->serving_type,
            'is_public' => $recipe->is_public,
            'editable' => $user->can('update', $recipe),
            'url' => route('recipes.show', $recipe),
        ])->all());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Part of the recipe name or of an ingredient (food), e.g. "Rösti" or "Kürbis".'),
            'category' => $schema->string()->description('Exact category name, see list-vocabulary.'),
            'cookbook' => $schema->string()->description('Exact cookbook name, see list-vocabulary.'),
            'tag' => $schema->string()->description('Exact tag name.'),
            'only_mine' => $schema->boolean()->description('Only recipes the user wrote.'),
            'limit' => $schema->integer()->description('Maximum number of recipes (1–50, default 20).'),
        ];
    }
}
