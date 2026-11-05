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

        return Response::text(RecipeJsonSchema::describe()."\n\nExample:\n".RecipeJsonSchema::exampleJson()
            ."\n\nPhotos are not accepted in create-recipe; add them afterwards with add-recipe-photo or generate-recipe-photo."
            ."\n\nNew foods created by create-recipe (listed in create) may get optional conversion and season data through its conversions argument (outside the recipe JSON): "
            .'{"Yuzu": {"measure": "mass", "density": null, "piece_weights": {"Stück": 80}, "season_months": [], "storage_months": []}}. '
            .'season_months are the months (1-12, each once) of fresh Swiss harvest per the Swiss seasonal calendar, storage_months the months '
            .'only available from Swiss storage; empty for foods without a Swiss season (spices, oil, pasta, imported fruit).');
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
