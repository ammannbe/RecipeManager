<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tokens of AI clients only work on the MCP server, where their permissions apply; the JSON API
 * belongs to the companion apps.
 */
class DenyMcpTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->tokenCan(Registrar::OAUTH_SCOPE)) {
            return response()->json(['message' => 'AI client tokens are only valid for /mcp.'], 403);
        }

        return $next($request);
    }
}
