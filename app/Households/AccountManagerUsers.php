<?php

namespace App\Households;

use App\Jobs\ReportAccountManagerUser;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Reports this app's users to the AccountManager (ACCOUNT_MANAGER_REPORT_USERS, AccountManager
 * docs/SERVICE-USERS-API.md): the queued ReportAccountManagerUser after sign-in, registration, link/unlink,
 * e-mail or author name changes and deletion (soft deleted users count as deleted), plus the full sync of
 * account-manager:report-users (daily). Failures never reach the user; the next sync repairs the state.
 *
 * @phpstan-import-type ServiceUser from AccountManagerClient
 */
class AccountManagerUsers
{
    /**
     * Users per request of the full sync (the AccountManager accepts up to 5000).
     */
    public const CHUNK = 5000;

    /**
     * Users per request of this instance (tests use smaller chunks).
     */
    public int $chunkSize = self::CHUNK;

    public static function enabled(): bool
    {
        return (bool) config('households.account_manager.report_users') && AccountManagerClient::configured();
    }

    /**
     * Queues the report of a user's current state (upsert, or delete when the user is gone or trashed).
     */
    public static function report(User|int $user): void
    {
        if (! self::enabled()) {
            return;
        }

        ReportAccountManagerUser::dispatch($user instanceof User ? (int) $user->getKey() : $user)->afterCommit();
    }

    /**
     * @return ServiceUser
     */
    public static function payload(User $user): array
    {
        $name = trim((string) $user->author?->name);

        return [
            'id' => (string) $user->getKey(),
            'email' => (string) $user->email,
            'name' => $name !== '' ? $name : null,
            'oidc_sub' => $user->oidc_sub,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Full sync: every user that is not trashed, in chunks of one sync (X-Sync-Started-At); the AccountManager
     * removes the users it did not see with the last chunk.
     *
     * @return array{upserted: int, removed: int}
     *
     * @throws AccountManagerUnavailable
     */
    public function sync(AccountManagerClient $client): array
    {
        $startedAt = now()->toIso8601String();
        $total = User::query()->count();
        $sent = 0;
        $result = ['upserted' => 0, 'removed' => 0];

        if ($total === 0) {
            return $client->putUsers([], true, $startedAt);
        }

        foreach (User::query()->with('author')->orderBy('id')->lazyById($this->chunkSize)->chunk($this->chunkSize) as $users) {
            $payload = array_values($users->map(fn (User $user): array => self::payload($user))->all());
            $sent += count($payload);

            $answer = $client->putUsers($payload, $sent >= $total, $startedAt);
            $result['upserted'] += $answer['upserted'];
            $result['removed'] += $answer['removed'];
        }

        // Users deleted while syncing: no chunk was marked complete, so finish the sync without them.
        if ($sent < $total) {
            $result['removed'] += $client->putUsers([], true, $startedAt)['removed'];
        }

        return $result;
    }

    public static function logUnavailable(AccountManagerUnavailable $exception, string $context): void
    {
        Log::warning("Service users: {$context} failed.", ['exception' => $exception->getMessage()]);
    }
}
