<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddRecipePhotoTool;
use App\Mcp\Tools\CreateRecipeTool;
use App\Mcp\Tools\DeleteRecipeTool;
use App\Mcp\Tools\FindSimilarTool;
use App\Mcp\Tools\GenerateRecipePhotoTool;
use App\Mcp\Tools\GetRecipeFormatTool;
use App\Mcp\Tools\GetRecipeTool;
use App\Mcp\Tools\ListVocabularyTool;
use App\Mcp\Tools\SearchRecipesTool;
use App\Mcp\Tools\UpdateRecipeTool;
use App\Services\SwissSpelling;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\ServerContext;

/**
 * MCP server for AI clients. OAuth through Passport (routes/ai.php); which tools a client sees
 * depends on the permissions the user gave it (McpGrant, see App\Mcp\Tools\RecipeTool).
 */
#[Name('RecipeManager')]
#[Version('1.0.0')]
#[Instructions('Recipes of the user\'s recipe collection (Swiss German recipes, units like g, dl, EL, TL). Search first, then read a recipe by id. Only tools the user allowed for this client are available; if one is missing, the user can change the permissions under "Connected AI clients" in the app. Never delete without asking the user. Before creating a recipe, check unknown names with find-similar and ask the user instead of creating near-duplicates.')]
class RecipesServer extends Server
{
    protected array $tools = [
        SearchRecipesTool::class,
        GetRecipeTool::class,
        ListVocabularyTool::class,
        FindSimilarTool::class,
        GetRecipeFormatTool::class,
        CreateRecipeTool::class,
        UpdateRecipeTool::class,
        AddRecipePhotoTool::class,
        GenerateRecipePhotoTool::class,
        DeleteRecipeTool::class,
    ];

    /**
     * Adds the spelling rule (RECIPE_SPELLING) to the instructions; attributes cannot depend on config.
     */
    public function createContext(): ServerContext
    {
        $context = parent::createContext();
        $context->instructions = trim($context->instructions.' '.SwissSpelling::instruction());

        return $context;
    }
}
