<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\Tag;
use App\Services\RecipeImport\RecipeJsonSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update-recipe')]
#[Description('Changes a recipe the user may edit: name, category, cookbook, servings, complexity, preparation time, preparation steps (Markdown), tags, source and visibility. Only the given fields change. Ingredients are changed on the recipe page in the app (see edit_url).')]
#[IsIdempotent]
class UpdateRecipeTool extends RecipeTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::Update;
    }

    public function handle(Request $request): Response
    {
        $user = $this->user($request);
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'string', 'max:'.RecipeJsonSchema::MAX_NAME],
            'category' => ['sometimes', 'string', 'max:'.RecipeJsonSchema::MAX_CATEGORY_NAME],
            'cookbook_id' => ['sometimes', 'nullable', 'integer'],
            'servings' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999'],
            'serving_type' => ['sometimes', 'nullable', 'string', 'max:'.RecipeJsonSchema::MAX_SERVING_TYPE],
            'complexity' => ['sometimes', 'string', 'in:'.implode(',', RecipeJsonSchema::complexities())],
            'preparation_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'instructions' => ['sometimes', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:'.RecipeJsonSchema::MAX_TAG_NAME],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        /** @var Recipe|null $recipe */
        $recipe = Recipe::query()->find((int) $data['id']);

        if ($recipe === null || ! $user->can('update', $recipe)) {
            return Response::error('No recipe with this id that you may edit.');
        }

        $attributes = collect($data)->only(['name', 'servings', 'serving_type', 'complexity', 'preparation_time', 'source', 'is_public'])->all();

        if (array_key_exists('category', $data)) {
            $category = Category::query()->whereRaw('lower(name) = ?', [mb_strtolower($data['category'])])->first();

            if ($category === null) {
                return Response::error('Unknown category. See list-vocabulary.');
            }

            $attributes['category_id'] = $category->id;
        }

        if (array_key_exists('cookbook_id', $data)) {
            if ($data['cookbook_id'] !== null && ! Cookbook::query()->administeredBy($user)->whereKey($data['cookbook_id'])->exists()) {
                return Response::error('You may not add recipes to this cookbook.');
            }

            $attributes['cookbook_id'] = $data['cookbook_id'];
        }

        if (array_key_exists('instructions', $data)) {
            // Same as the import: rendered as HTML on the recipe page, so never let raw HTML through.
            $attributes['instructions'] = Str::markdown($data['instructions'], ['html_input' => 'escape', 'allow_unsafe_links' => false]);
        }

        $tagIds = null;

        if (array_key_exists('tags', $data)) {
            $tags = Tag::query()->visibleTo($user)->get()->keyBy(fn (Tag $tag): string => mb_strtolower($tag->name));
            /** @var list<string> $names */
            $names = $data['tags'];
            $unknown = collect($names)->reject(fn (string $name): bool => $tags->has(mb_strtolower($name)));

            if ($unknown->isNotEmpty()) {
                return Response::error('Unknown tags: '.$unknown->implode(', ').'. Use existing tags (list-vocabulary).');
            }

            $tagIds = collect($names)->map(fn (string $name): int => $tags[mb_strtolower($name)]->id)->unique()->all();
        }

        $recipe->update($attributes);

        if ($tagIds !== null) {
            $recipe->tags()->sync($tagIds);
        }

        return Response::json([
            'saved' => true,
            'id' => $recipe->id,
            'changed' => array_keys(collect($data)->except('id')->all()),
            'url' => route('recipes.show', $recipe),
            'edit_url' => route('filament.app.resources.recipes.edit', ['record' => $recipe]),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The recipe id.')->required(),
            'name' => $schema->string(),
            'category' => $schema->string()->description('Existing category name.'),
            'cookbook_id' => $schema->integer()->description('Cookbook id the user may add recipes to, or null for none.'),
            'servings' => $schema->number(),
            'serving_type' => $schema->string()->description('e.g. "Portionen", "Stück".'),
            'complexity' => $schema->string()->enum(RecipeJsonSchema::complexities()),
            'preparation_time' => $schema->string()->description('Total time as HH:MM.'),
            'instructions' => $schema->string()->description('All preparation steps as Markdown (numbered list); replaces the old ones.'),
            'tags' => $schema->array()->items($schema->string())->description('Existing tag names; replaces the old ones.'),
            'source' => $schema->string(),
            'is_public' => $schema->boolean(),
        ];
    }
}
