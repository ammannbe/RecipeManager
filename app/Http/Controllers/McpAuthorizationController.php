<?php

namespace App\Http\Controllers;

use App\Mcp\McpPermission;
use App\Models\McpGrant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\User;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Approval of an AI client (MCP): stores the permissions the user ticked for the client, then
 * hands over to Passport's approval, which issues the authorization code. laravel/mcp only knows
 * one OAuth scope, so the permissions live in mcp_grants instead of token scopes.
 */
class McpAuthorizationController extends Controller
{
    public function approve(Request $request): Response
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::enum(McpPermission::class)],
        ]);

        // Passport pulls the request from the session in its controller, so only peek here.
        $serialized = $request->session()->get('authRequest');
        $authRequest = is_string($serialized) ? unserialize($serialized, ['allowed_classes' => [
            AuthorizationRequest::class,
            Client::class,
            Scope::class,
            User::class,
        ]]) : null;

        abort_unless($authRequest instanceof AuthorizationRequest, 400);

        McpGrant::query()->updateOrCreate(
            ['user_id' => $request->user()?->getAuthIdentifier(), 'oauth_client_id' => $authRequest->getClient()->getIdentifier()],
            ['permissions' => array_values(array_unique($validated['permissions']))],
        );

        /** @var Response */
        return app()->call([app(ApproveAuthorizationController::class), 'approve']);
    }
}
