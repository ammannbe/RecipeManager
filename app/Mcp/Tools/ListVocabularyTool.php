<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\IngredientAttribute;
use App\Models\Tag;
use App\Models\Unit;
use App\Services\RecipeImport\RecipeJsonSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-vocabulary')]
#[Description('Lists the known categories, cookbooks (with whether the user may add recipes), units, ingredient attributes, tags and complexities. Use these exact names in search-recipes and create-recipe.')]
#[IsReadOnly]
class ListVocabularyTool extends RecipeTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::Read;
    }

    public function handle(Request $request): Response
    {
        $user = $this->user($request);
        $editable = Cookbook::query()->administeredBy($user)->pluck('id')->all();

        return Response::json([
            'categories' => Category::query()->orderBy('name')->pluck('name')->all(),
            'cookbooks' => Cookbook::query()->visibleTo($user)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Cookbook $cookbook): array => ['id' => $cookbook->id, 'name' => $cookbook->name, 'can_add_recipes' => in_array($cookbook->id, $editable, true)])
                ->all(),
            'units' => Unit::query()->visibleTo($user)->orderBy('name')->get()
                ->map(fn (Unit $unit): string => $unit->name_shortcut !== null ? "{$unit->name} ({$unit->name_shortcut})" : $unit->name)
                ->all(),
            'ingredient_attributes' => IngredientAttribute::query()->visibleTo($user)->orderBy('name')->pluck('name')->all(),
            'tags' => Tag::query()->visibleTo($user)->orderBy('name')->pluck('name')->all(),
            'complexities' => RecipeJsonSchema::complexities(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
