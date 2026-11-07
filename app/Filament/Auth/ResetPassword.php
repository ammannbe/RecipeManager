<?php

namespace App\Filament\Auth;

use App\Auth\OidcAccount;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * A linked account never gets a password through a reset link, also not through a link that
 * was sent before the account was linked (linking deletes the tokens, this is the safety net).
 */
class ResetPassword extends BaseResetPassword
{
    public function resetPassword(): ?PasswordResetResponse
    {
        $user = User::query()->where('email', (string) $this->email)->first();

        if ($user?->isLinkedToOidc()) {
            Password::broker(Filament::getAuthPasswordBroker())->deleteToken($user);

            Notification::make()->danger()->title(OidcAccount::message())->persistent()->send();

            return null;
        }

        return parent::resetPassword();
    }
}
