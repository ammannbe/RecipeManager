<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Services\RecipeImport\RecipeJsonSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-recipe-format')]
#[Description('Describes the recipe JSON format create-recipe expects, with a complete example.')]
#[IsReadOnly]
class GetRecipeFormatTool extends RecipeTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::Create;
    }

    public function handle(Request $request): Response
    {
        $this->user($request);

        return Response::text(RecipeJsonSchema::describe()."\n\nExample:\n".RecipeJsonSchema::exampleJson()."\n\nPhotos are not accepted through MCP.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
