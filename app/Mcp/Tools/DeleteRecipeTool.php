<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Models\Recipe;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete-recipe')]
#[Description('Deletes a recipe the user may delete. It goes to the trash and an admin can restore it. Ask the user before calling.')]
#[IsDestructive]
class DeleteRecipeTool extends RecipeTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::Delete;
    }

    public function handle(Request $request): Response
    {
        $user = $this->user($request);
        $data = $request->validate(['id' => ['required', 'integer']]);

        /** @var Recipe|null $recipe */
        $recipe = Recipe::query()->find((int) $data['id']);

        if ($recipe === null || ! $user->can('delete', $recipe)) {
            return Response::error('No recipe with this id that you may delete.');
        }

        $recipe->delete();

        return Response::text("Deleted \"{$recipe->name}\" (id {$recipe->id}).");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The recipe id.')->required(),
        ];
    }
}
