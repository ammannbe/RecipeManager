<?php

namespace App\Auth;

use App\Models\User;

/**
 * Banner in the panel offering unlinked users to link the OIDC sign-in (AUTH_OIDC_NUDGE). "Later" stores
 * users.oidc_nudge_dismissed_at; AUTH_OIDC_NUDGE_DAYS > 0 shows it again after that many days, otherwise never.
 * The button opens the profile's "Link …" action (password confirmation, then OidcController "link").
 */
class OidcNudge
{
    public static function shows(?User $user): bool
    {
        if (! $user instanceof User || ! config('login.oidc.enabled') || ! config('login.oidc.nudge') || $user->isLinkedToOidc()) {
            return false;
        }

        $dismissedAt = $user->oidc_nudge_dismissed_at;

        if ($dismissedAt === null) {
            return true;
        }

        $days = (int) config('login.oidc.nudge_days');

        return $days > 0 && $dismissedAt->lte(now()->subDays($days));
    }

    public static function dismiss(User $user): void
    {
        $user->forceFill(['oidc_nudge_dismissed_at' => now()])->saveQuietly();
    }
}
