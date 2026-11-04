<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateRecipeTool;
use App\Mcp\Tools\DeleteRecipeTool;
use App\Mcp\Tools\GetRecipeFormatTool;
use App\Mcp\Tools\GetRecipeTool;
use App\Mcp\Tools\ListVocabularyTool;
use App\Mcp\Tools\SearchRecipesTool;
use App\Mcp\Tools\UpdateRecipeTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * MCP server for AI clients. OAuth through Passport (routes/ai.php); which tools a client sees
 * depends on the permissions the user gave it (McpGrant, see App\Mcp\Tools\RecipeTool).
 */
#[Name('RecipeManager')]
#[Version('1.0.0')]
#[Instructions('Recipes of the user\'s recipe collection (Swiss German recipes, units like g, dl, EL, TL). Search first, then read a recipe by id. Only tools the user allowed for this client are available; if one is missing, the user can change the permissions under "Connected AI clients" in the app. Never delete without asking the user.')]
class RecipesServer extends Server
{
    protected array $tools = [
        SearchRecipesTool::class,
        GetRecipeTool::class,
        ListVocabularyTool::class,
        GetRecipeFormatTool::class,
        CreateRecipeTool::class,
        UpdateRecipeTool::class,
        DeleteRecipeTool::class,
    ];
}
