<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Services\RecipeImport\LookupType;
use App\Services\RecipeImport\RelationResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('find-similar')]
#[Description('Checks names of foods, units, ingredient attributes, tags, categories or cookbooks against the existing entries the user can use, the same way create-recipe matches them: per name the exact match (also singular/plural), otherwise similar entries, or none. Use it before create-recipe and ask the user when there are similar entries instead of creating a new one.')]
#[IsReadOnly]
class FindSimilarTool extends RecipeTool
{
    private const TYPES = ['food', 'unit', 'attribute', 'tag', 'category', 'cookbook'];

    public function __construct(private RelationResolver $resolver) {}

    protected function permission(): McpPermission
    {
        return McpPermission::Read;
    }

    public function handle(Request $request): Response
    {
        $user = $this->user($request);
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'names' => ['required', 'array', 'min:1', 'max:50'],
            'names.*' => ['required', 'string', 'max:100'],
        ]);

        $type = LookupType::from($data['type']);
        $results = [];

        /** @var list<string> $names */
        $names = $data['names'];

        foreach ($names as $name) {
            $existing = $this->resolver->existing($type, $name, $user);

            if ($existing !== null) {
                $results[] = ['name' => $name, 'status' => 'exact', 'match' => ['id' => (int) $existing->getKey(), 'name' => (string) $existing->getAttribute('name')]];

                continue;
            }

            $similar = $this->resolver->suggest($type, $name, $user);

            $results[] = [
                'name' => $name,
                'status' => $similar !== [] ? 'similar' : 'none',
                'similar' => $similar,
            ];
        }

        return Response::json([
            'type' => $type->value,
            'results' => $results,
            'hint' => 'exact: use the matched name. similar: ask the user whether one of them is meant (create-recipe map) or a new entry should be created (create-recipe create). none: may be created when the user agrees.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(self::TYPES)->description('Kind of the names.')->required(),
            'names' => $schema->array()->items($schema->string())->description('Names to check, e.g. ["Rüebli", "Yuzu"]. At most 50.')->required(),
        ];
    }
}
