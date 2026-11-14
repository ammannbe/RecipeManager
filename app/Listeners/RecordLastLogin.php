<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Throwable;

/**
 * users.last_login_at for the AccountManager's user overview (ACCOUNT_MANAGER_REPORT_USERS; UserObserver reports
 * the change). Also counted for remember-me sign-ins. Never breaks the sign-in.
 */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;

        try {
            $user->timestamps = false;
            $user->forceFill(['last_login_at' => now()])->save();
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            $user->timestamps = true;
        }
    }
}
