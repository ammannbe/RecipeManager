<?php

namespace App\Entitlements;

use App\Households\AccountManagerClient;
use App\Models\User;
use App\Models\UserEntitlement;

/**
 * What a user may do in this app: access, admin rights and features (contract: AccountManager
 * docs/ENTITLEMENTS-API.md, concept 5.3).
 *
 * ENTITLEMENTS_DRIVER=local: everybody has access and the local features (config/entitlements.php).
 * ENTITLEMENTS_DRIVER=account-manager: linked users (oidc_sub) get the stored snapshot of the
 * AccountManager; without a snapshot (never synced) they keep access but have no features. Users
 * without a linked sign-in (emergency admins) keep the local behaviour.
 * AUTH_ADMIN_SOURCE=account-manager: linked users are admins when the snapshot says so, users without
 * a linked sign-in keep users.admin.
 */
class Entitlements
{
    public const ACTIVE = 'active';

    public const REQUESTED = 'requested';

    public const SUSPENDED = 'suspended';

    public const NONE = 'none';

    public const STATUSES = [self::ACTIVE, self::REQUESTED, self::SUSPENDED, self::NONE];

    public static function usesAccountManager(): bool
    {
        return config('entitlements.driver') === 'account-manager';
    }

    public static function adminFromAccountManager(): bool
    {
        return config('entitlements.admin_source') === 'account-manager';
    }

    /**
     * Snapshots are stored (webhook, sign-in, daily sync) when the driver or the admin source is the
     * AccountManager and it is configured.
     */
    public static function syncEnabled(): bool
    {
        return (self::usesAccountManager() || self::adminFromAccountManager()) && AccountManagerClient::configured();
    }

    public static function has(?User $user, string $feature): bool
    {
        return in_array($feature, self::features($user), true);
    }

    /**
     * @return list<string>
     */
    public static function features(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if (! self::usesAccountManager() || $user->oidc_sub === null) {
            return array_values(array_map('strval', (array) config('entitlements.local.features', [])));
        }

        $snapshot = self::snapshot($user);

        return $snapshot?->status === self::ACTIVE ? $snapshot->features : [];
    }

    public static function isAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! self::adminFromAccountManager() || $user->oidc_sub === null) {
            return (bool) $user->admin;
        }

        $snapshot = self::snapshot($user);

        return $snapshot?->status === self::ACTIVE && $snapshot->admin;
    }

    /**
     * Access status: "active" with the local driver and for users without a linked sign-in, the
     * stored status for linked users, null when they were never synced.
     */
    public static function status(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if (! self::usesAccountManager() || $user->oidc_sub === null) {
            return self::ACTIVE;
        }

        return self::snapshot($user)?->status;
    }

    /**
     * A suspended, requested or removed access blocks the app; an unknown state (AccountManager
     * not asked yet) does not lock anybody out.
     */
    public static function allowsAccess(User $user): bool
    {
        $status = self::status($user);

        return $status === null || $status === self::ACTIVE;
    }

    public static function deniedMessage(User $user): string
    {
        return (string) self::messageFor(self::deniedReason($user));
    }

    /**
     * Reason for the login page after a blocked user was signed out ("suspended" or "disabled").
     */
    public static function deniedReason(User $user): string
    {
        return self::status($user) === self::SUSPENDED ? 'suspended' : 'disabled';
    }

    public static function messageFor(string $reason): ?string
    {
        $app = (string) config('app.name');

        return match ($reason) {
            'suspended' => (string) __('Your access to :app is suspended.', ['app' => $app]),
            'disabled' => (string) __('Your access to :app is not enabled.', ['app' => $app]),
            default => null,
        };
    }

    private static function snapshot(User $user): ?UserEntitlement
    {
        return $user->entitlement;
    }
}
