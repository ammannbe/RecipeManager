<?php

namespace App\Listeners;

use App\Http\Controllers\HouseholdInvitationController;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Services\Households;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Session;

/**
 * A guest opened a household invitation link, then registered: verifying the address
 * completes the invitation they clicked. Other invitations to the address stay pending
 * until accepted on the households page.
 */
class AcceptHouseholdInvitationFromSession
{
    public function __construct(
        private Households $households,
    ) {}

    public function handle(Verified $event): void
    {
        $token = Session::get(HouseholdInvitationController::SESSION_KEY);

        if (! $event->user instanceof User || ! is_string($token)) {
            return;
        }

        $invitation = HouseholdInvitation::query()
            ->pending()
            ->where('token_hash', HouseholdInvitation::hash($token))
            ->first();

        if ($invitation !== null && $this->households->accept($invitation, $event->user)) {
            Session::forget(HouseholdInvitationController::SESSION_KEY);
        }
    }
}
