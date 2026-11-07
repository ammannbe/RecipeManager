<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Households\HouseholdResource;
use App\Models\HouseholdInvitation;
use App\Services\Households;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class HouseholdInvitationController extends Controller
{
    public const SESSION_KEY = 'household_invitation_token';

    public function show(string $token, Households $households): RedirectResponse
    {
        if (! Households::isLocal()) {
            return redirect()
                ->route('recipes.index')
                ->with('error', __('Households are managed in the central account and cannot be joined here.'));
        }

        $invitation = HouseholdInvitation::query()
            ->pending()
            ->where('token_hash', HouseholdInvitation::hash($token))
            ->first();

        if ($invitation === null) {
            return redirect()
                ->route('recipes.index')
                ->with('error', __('This invitation is no longer valid.'));
        }

        $user = user();

        if ($user === null || ! $user->hasVerifiedEmail()) {
            // Remember the token: logging in returns here, verifying the address accepts it.
            Session::put(self::SESSION_KEY, $token);

            return $user === null
                ? redirect()->guest(route('filament.app.auth.login'))
                : redirect()->route('filament.app.auth.email-verification.prompt');
        }

        if (! $households->accept($invitation, $user)) {
            return redirect()
                ->route('recipes.index')
                ->with('error', __('This invitation was sent to a different email address.'));
        }

        Session::forget(self::SESSION_KEY);

        Notification::make()
            ->success()
            ->title(__('You joined the household ":name".', ['name' => $invitation->household->name]))
            ->send();

        return redirect(HouseholdResource::getUrl('view', ['record' => $invitation->household]));
    }
}
