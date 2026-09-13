<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\CookbookSharing;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;

class AcceptPendingCookbookInvitations
{
    public function __construct(
        private CookbookSharing $sharing,
    ) {}

    public function handle(Registered|Verified $event): void
    {
        if ($event->user instanceof User) {
            $this->sharing->acceptPendingFor($event->user);
        }
    }
}
