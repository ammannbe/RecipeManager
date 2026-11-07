<?php

namespace App\Http\Middleware;

use App\Entitlements\Entitlements;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ENTITLEMENTS_DRIVER=account-manager: a linked user whose access is suspended, requested or removed in
 * the AccountManager is signed out on the next request (web) or gets 403 (API, MCP). Users without a
 * linked sign-in (emergency admins) are never blocked.
 */
class EnsureEntitlementAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || Entitlements::allowsAccess($user)) {
            return $next($request);
        }

        $message = Entitlements::deniedMessage($user);

        if ($request->is('api/*', 'mcp') || $request->bearerToken() !== null) {
            return response()->json(['message' => $message], 403);
        }

        $reason = Entitlements::deniedReason($user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The login page shows the message (also when Livewire follows the redirect with fetch first).
        return redirect()->to(Filament::getPanel('app')->getLoginUrl(['access' => $reason]));
    }
}
