<?php

namespace App\Notifications;

use App\Auth\OidcLabel;
use Filament\Facades\Filament;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent instead of a reset link when a linked account asks for a password reset.
 */
class SignInWithOidcNotice extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $provider = OidcLabel::get();

        return (new MailMessage)
            ->subject(__('Sign in with :provider', ['provider' => $provider]))
            ->line(__('Someone asked to reset the password of your :app account.', ['app' => config('app.name')]))
            ->line(__('Your account is connected to :provider and has no password. Sign in with :provider instead.', ['provider' => $provider]))
            ->action(__('Sign in with :provider', ['provider' => $provider]), Filament::getPanel('app')->getLoginUrl())
            ->line(__('If you did not ask for this, you can ignore this e-mail.'));
    }
}
