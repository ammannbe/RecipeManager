<?php

namespace App\Http\Middleware;

use App\Models\McpGrant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * The MCP server only answers tokens issued to AI clients (scope mcp:use) that the user gave
 * permissions on the approval screen; tokens of companion apps like Shoppinglist are refused.
 */
class EnsureMcpGrant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $grant = $user instanceof User && $user->tokenCan(Registrar::OAUTH_SCOPE) ? McpGrant::current($user) : null;

        if ($grant === null) {
            return response()->json(['error' => 'access_denied', 'error_description' => 'This token is not connected as an AI client. Connect again to choose its permissions.'], 403);
        }

        // At most once a minute, it is shown in "Connected AI clients".
        if ($grant->last_used_at === null || $grant->last_used_at->lt(now()->subMinute())) {
            $grant->forceFill(['last_used_at' => now()])->save();
        }

        return $next($request);
    }
}
