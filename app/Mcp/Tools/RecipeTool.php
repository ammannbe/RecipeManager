<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Models\McpGrant;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

/**
 * A tool is only listed and callable when the user gave the AI client its permission
 * (McpGrant). The user's own rights still apply on top (RecipePolicy, visibleTo()).
 */
abstract class RecipeTool extends Tool
{
    abstract protected function permission(): McpPermission;

    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && (McpGrant::current($user)?->allows($this->permission()) ?? false);
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $this->shouldRegister($request), 403);

        return $user;
    }
}
