<?php

use App\Http\Middleware\EnsureEntitlementAccess;
use App\Http\Middleware\EnsureMcpGrant;
use App\Mcp\Servers\RecipesServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// OAuth discovery and dynamic client registration for AI clients (Claude Code, Codex, OpenClaw …).
// Registration is public by design (RFC 7591), so throttle it.
Route::middleware('throttle:10,1')->group(function (): void {
    Mcp::oauthRoutes();
});

Mcp::web('/mcp', RecipesServer::class)
    ->middleware(['auth:api', EnsureMcpGrant::class, EnsureEntitlementAccess::class, 'throttle:120,1']);
