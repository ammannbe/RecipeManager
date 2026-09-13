<?php

namespace App\Services;

use App\Mail\CookbookInvitationMail;
use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\CookbookMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CookbookSharing
{
    public const EXPIRES_AFTER_DAYS = 14;

    /**
     * Shares a cookbook with an email address. Existing users are added straight away,
     * everyone else receives an invitation.
     *
     * Returns nothing on purpose: the caller must not be able to tell whether the
     * address belongs to an account.
     *
     * @param  array<string, bool>  $grants
     */
    public function share(Cookbook $cookbook, string $email, array $grants, User $invitedBy): void
    {
        $email = mb_strtolower(trim($email));

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user !== null) {
            $this->addMember($cookbook, $user, $grants);

            return;
        }

        $this->invite($cookbook, $email, $grants, $invitedBy);
    }

    /**
     * @param  array<string, bool>  $grants
     */
    public function addMember(Cookbook $cookbook, User $user, array $grants): void
    {
        // The owner already holds every grant; a membership row would be meaningless.
        if ($cookbook->isOwnedBy($user)) {
            return;
        }

        $cookbook->members()->syncWithoutDetaching([
            $user->getKey() => $this->normalise($grants),
        ]);
    }

    /**
     * @param  array<string, bool>  $grants
     */
    private function invite(Cookbook $cookbook, string $email, array $grants, User $invitedBy): void
    {
        $token = CookbookInvitation::newToken();

        $invitation = $cookbook->invitations()->updateOrCreate(
            ['email' => $email],
            [
                ...$this->normalise($grants),
                'invited_by_user_id' => $invitedBy->getKey(),
                'token_hash' => CookbookInvitation::hash($token),
                'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
                'accepted_at' => null,
            ],
        );

        Mail::to($email)->send(new CookbookInvitationMail($invitation, $token));
    }

    /**
     * Binds a pending invitation to a user whose email matches it.
     */
    public function accept(CookbookInvitation $invitation, User $user): bool
    {
        // Possession of the token is not enough; the account must own the address.
        if (! $invitation->isPending() || mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            return false;
        }

        DB::transaction(function () use ($invitation, $user): void {
            $this->addMember($invitation->cookbook, $user, $invitation->grants());

            $invitation->forceFill(['accepted_at' => now()])->save();
        });

        return true;
    }

    /**
     * Binds every pending invitation for a freshly registered address.
     */
    public function acceptPendingFor(User $user): void
    {
        CookbookInvitation::query()
            ->pending()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])
            ->with('cookbook')
            ->each(fn (CookbookInvitation $invitation) => $this->accept($invitation, $user));
    }

    /**
     * @param  array<string, bool>  $grants
     * @return array<string, bool>
     */
    private function normalise(array $grants): array
    {
        $normalised = [];

        foreach (CookbookMembership::GRANTS as $grant) {
            $normalised[$grant] = (bool) ($grants[$grant] ?? false);
        }

        // Read access is implied by every other grant.
        if (array_filter($normalised)) {
            $normalised['can_read'] = true;
        }

        return $normalised;
    }
}
