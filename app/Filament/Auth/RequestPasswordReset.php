<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Notifications\SignInWithOidcNotice;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A linked account gets no reset link (it has no password and must not get one), but a mail
 * that points to the OIDC provider. The page shows the same as for any other account, so it
 * does not tell whether the account is linked.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    /**
     * One notice per account in this time, like the broker's reset link throttle.
     */
    public const NOTICE_DECAY_SECONDS = 15 * 60;

    public function request(): void
    {
        $email = $this->form->getState()['email'] ?? null;
        $user = is_string($email) ? User::query()->where('email', $email)->whereNotNull('oidc_sub')->first() : null;

        if (! $user) {
            parent::request();

            return;
        }

        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        RateLimiter::attempt(
            'oidc-reset-notice:'.$user->getKey(),
            1,
            fn () => $user->notify((new SignInWithOidcNotice)->locale($user->locale ?? config('app.locale'))),
            self::NOTICE_DECAY_SECONDS,
        );

        $this->getSentNotification(Password::RESET_LINK_SENT)?->send();

        $this->form->fill();
    }
}
