<?php

namespace App\Http\Controllers;

use App\Models\CookbookInvitation;
use App\Services\CookbookSharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class CookbookInvitationController extends Controller
{
    public const SESSION_KEY = 'cookbook_invitation_token';

    public function show(string $token, CookbookSharing $sharing): RedirectResponse
    {
        $invitation = CookbookInvitation::query()
            ->pending()
            ->where('token_hash', CookbookInvitation::hash($token))
            ->first();

        if ($invitation === null) {
            return redirect()
                ->route('recipes.index')
                ->with('error', __('This invitation is no longer valid.'));
        }

        $user = user();

        if ($user === null) {
            // Remember the token so registering or logging in completes the invitation.
            Session::put(self::SESSION_KEY, $token);

            return redirect()->route('filament.app.auth.login');
        }

        if (! $sharing->accept($invitation, $user)) {
            return redirect()
                ->route('recipes.index')
                ->with('error', __('This invitation was sent to a different email address.'));
        }

        Session::forget(self::SESSION_KEY);

        return redirect()
            ->route('filament.app.resources.cookbooks.index')
            ->with('status', __('The cookbook ":name" has been shared with you.', [
                'name' => $invitation->cookbook->name,
            ]));
    }
}
