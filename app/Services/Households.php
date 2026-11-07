<?php

namespace App\Services;

use App\Enums\HouseholdRole;
use App\Exceptions\LastHouseholdAdmin;
use App\Mail\HouseholdInvitationMail;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Local household management (HOUSEHOLDS_DRIVER=local). Authorization lives in
 * HouseholdPolicy; this class keeps the invariants: every household keeps an admin,
 * invitations bind only to the invited, verified address, and leaving or deleting
 * only removes access (contents stay with their owner).
 */
class Households
{
    public const EXPIRES_AFTER_DAYS = 14;

    /**
     * Whether households are managed in this app. With "konto" they are mirrored from the
     * AccountManager and read-only here.
     */
    public static function isLocal(): bool
    {
        return config('households.driver', 'local') === 'local';
    }

    public function create(User $user, string $name): Household
    {
        return DB::transaction(function () use ($user, $name): Household {
            $household = Household::query()->create([
                'name' => trim($name),
                'created_by' => $user->getKey(),
            ]);

            $household->users()->attach($user->getKey(), [
                'role' => HouseholdRole::Admin->value,
                'joined_at' => now(),
            ]);

            return $household;
        });
    }

    public function rename(Household $household, string $name): void
    {
        $household->update(['name' => trim($name)]);
    }

    /**
     * Memberships, invitations and cookbook shares go with it (FK cascades); the cookbooks
     * and recipes stay with their owners.
     */
    public function delete(Household $household): void
    {
        $household->delete();
    }

    /**
     * Sends an invitation, also to existing users: joining a household needs their consent.
     * Returns nothing on purpose, so the caller cannot tell whether the address has an account.
     */
    public function invite(Household $household, string $email, HouseholdRole $role, User $invitedBy): void
    {
        $email = mb_strtolower(trim($email));

        $alreadyMember = $household->users()->whereRaw('LOWER(users.email) = ?', [$email])->exists();

        if ($alreadyMember) {
            return;
        }

        $token = HouseholdInvitation::newToken();

        $invitation = $household->invitations()->updateOrCreate(
            ['email' => $email],
            [
                'role' => $role->value,
                'invited_by' => $invitedBy->getKey(),
                'token_hash' => HouseholdInvitation::hash($token),
                'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
                'accepted_at' => null,
            ],
        );

        Mail::to($email)->send(new HouseholdInvitationMail($invitation, $token));
    }

    /**
     * Adds the user to the household of a pending invitation sent to their verified address.
     */
    public function accept(HouseholdInvitation $invitation, User $user): bool
    {
        if (! $this->canAccept($invitation, $user)) {
            return false;
        }

        DB::transaction(function () use ($invitation, $user): void {
            $household = $invitation->household;

            if (! $household->hasMember($user)) {
                $household->users()->attach($user->getKey(), [
                    'role' => $invitation->role->value,
                    'joined_at' => now(),
                ]);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();
        });

        return true;
    }

    public function decline(HouseholdInvitation $invitation, User $user): bool
    {
        if (! $this->canAccept($invitation, $user)) {
            return false;
        }

        $invitation->delete();

        return true;
    }

    /**
     * Possession of the token is not enough: the account must own the (verified) address.
     */
    public function canAccept(HouseholdInvitation $invitation, User $user): bool
    {
        return self::isLocal()
            && $invitation->isPending()
            && $user->hasVerifiedEmail()
            && mb_strtolower((string) $user->email) === mb_strtolower($invitation->email);
    }

    public function changeRole(Household $household, User $member, HouseholdRole $role): void
    {
        DB::transaction(function () use ($household, $member, $role): void {
            if ($role === HouseholdRole::Member) {
                $this->ensureAnotherAdmin($household, $member);
            }

            $household->users()->updateExistingPivot($member->getKey(), ['role' => $role->value]);
        });
    }

    /**
     * Removes the member's access to everything shared with the household.
     */
    public function removeMember(Household $household, User $member): void
    {
        DB::transaction(function () use ($household, $member): void {
            $this->ensureAnotherAdmin($household, $member);

            $household->users()->detach($member->getKey());
        });
    }

    public function leave(Household $household, User $user): void
    {
        $this->removeMember($household, $user);
    }

    /**
     * A user is deleted: drops their memberships. A household left without members is
     * deleted, one left without admin gets its longest-standing member as admin.
     */
    public function forget(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->households()->get()->each(function (Household $household) use ($user): void {
                $household->users()->detach($user->getKey());

                if (! self::isLocal()) {
                    return;
                }

                $memberships = HouseholdMembership::query()
                    ->where('household_id', $household->getKey())
                    ->orderBy('joined_at')
                    ->orderBy('id')
                    ->get();

                if ($memberships->isEmpty()) {
                    $household->delete();
                } elseif (! $memberships->contains('role', HouseholdRole::Admin)) {
                    $memberships->first()->forceFill(['role' => HouseholdRole::Admin])->save();
                }
            });
        });
    }

    /**
     * Throws when the user is the household's only admin. Locks the admin rows, so two
     * admins demoting each other at the same time cannot both succeed.
     */
    private function ensureAnotherAdmin(Household $household, User $member): void
    {
        $admins = HouseholdMembership::query()
            ->where('household_id', $household->getKey())
            ->where('role', HouseholdRole::Admin->value)
            ->lockForUpdate()
            ->pluck('user_id');

        if ($admins->count() === 1 && (int) $admins->first() === (int) $member->getKey()) {
            throw new LastHouseholdAdmin;
        }
    }
}
