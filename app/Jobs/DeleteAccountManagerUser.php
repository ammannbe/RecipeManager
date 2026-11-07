<?php

namespace App\Jobs;

use App\Entitlements\AccountManagerEntitlements;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Webhook user.deleted of the AccountManager (docs/ENTITLEMENTS-API.md 5.2): deletes the local user
 * with this subject and their private contents (queued, so the webhook answers fast).
 */
class DeleteAccountManagerUser implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $sub) {}

    public function handle(AccountManagerEntitlements $entitlements): void
    {
        $entitlements->handleDeleted($this->sub);
    }
}
