<?php

namespace App\Jobs;

use App\Households\AccountManagerClient;
use App\Households\AccountManagerUnavailable;
use App\Households\AccountManagerUsers;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reports one user's state to the AccountManager when the job runs (so jobs can never overtake each other):
 * upsert while the user exists, delete once it is trashed or gone. Failures are retried with a backoff and
 * then left to the daily account-manager:report-users.
 */
class ReportAccountManagerUser implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Seconds before the next try.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public int $userId) {}

    public function handle(AccountManagerClient $client): void
    {
        if (! AccountManagerUsers::enabled()) {
            return;
        }

        $user = User::query()->with('author')->find($this->userId);

        try {
            if ($user === null) {
                $client->deleteUser((string) $this->userId);
            } else {
                $client->upsertUser(AccountManagerUsers::payload($user));
            }
        } catch (AccountManagerUnavailable $exception) {
            AccountManagerUsers::logUnavailable($exception, "reporting user {$this->userId}");

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 300);
            }
        }
    }
}
