<?php

namespace App\Models;

use App\Enums\HouseholdRole;
use App\Jobs\ReportKontoShares;
use App\Services\Households;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of users that cookbooks can be shared with. Access is resolved through the
 * membership at runtime, so new members need no shares of their own and removed members
 * lose access right away; their own cookbooks are no longer shared with it. Contents always
 * stay with their owner.
 */
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'name',
        'created_by',
        'konto_household_uuid',
    ];

    protected $casts = [
        'konto_version' => 'integer',
    ];

    /**
     * Mirrored from the AccountManager (HOUSEHOLDS_DRIVER=konto): name and members come from there.
     * After switching back to "local" mirrored households are ordinary local ones.
     */
    public function isMirrored(): bool
    {
        return $this->konto_household_uuid !== null && ! Households::isLocal();
    }

    /**
     * Only the uuid column is generated; the primary key stays an auto-increment id.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this, HouseholdMembership>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'household_user')
            ->using(HouseholdMembership::class)
            ->withPivot(['role', 'joined_at']);
    }

    /**
     * @return HasMany<HouseholdInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class);
    }

    /**
     * HOUSEHOLDS_DRIVER=konto: whether the household comes from the AccountManager, or the state of
     * its proposal (households:import). Null with the local driver.
     */
    public function kontoStatus(): ?string
    {
        if (Households::isLocal()) {
            return null;
        }

        return match (true) {
            $this->konto_household_uuid !== null => __('Managed in your account'),
            $this->konto_import_status === 'pending' => __('Proposed for your account, waiting for confirmation'),
            $this->konto_import_status === 'rejected' => __('Not taken over into your account'),
            default => __('Only in this app'),
        };
    }

    /**
     * Members of the mirrored household without a user here yet (attached on their first OIDC sign-in).
     *
     * @return HasMany<HouseholdPendingMember, $this>
     */
    public function pendingMembers(): HasMany
    {
        return $this->hasMany(HouseholdPendingMember::class);
    }

    /**
     * Cookbooks shared with this household.
     *
     * @return BelongsToMany<Cookbook, $this, CookbookHouseholdShare>
     */
    public function cookbooks(): BelongsToMany
    {
        return $this->belongsToMany(Cookbook::class, 'cookbook_household')
            ->using(CookbookHouseholdShare::class)
            ->withPivot(CookbookHouseholdShare::GRANTS)
            ->withTimestamps();
    }

    /**
     * Removes the users: they lose access to the cookbooks shared with the household, and their own
     * cookbooks (also those in the trash) are no longer shared with it; the cookbooks stay with them.
     * Returns the number of unshared cookbooks; for mirrored households the change is reported.
     *
     * @param  array<int, int|string>  $userIds
     */
    public function detachMembers(array $userIds): int
    {
        $this->users()->detach($userIds);

        $authorIds = User::query()->whereKey($userIds)->whereNotNull('author_id')->pluck('author_id');

        if ($authorIds->isEmpty()) {
            return 0;
        }

        $unshared = CookbookHouseholdShare::query()
            ->where('household_id', $this->getKey())
            ->whereIn('cookbook_id', Cookbook::withTrashed()->withoutGlobalScope('author_name')->select('id')->whereIn('author_id', $authorIds))
            ->delete();

        if ($unshared > 0) {
            ReportKontoShares::dispatchFor($this);
        }

        return $unshared;
    }

    public function roleOf(?User $user): ?HouseholdRole
    {
        if ($user === null) {
            return null;
        }

        $role = HouseholdMembership::query()
            ->where('household_id', $this->getKey())
            ->where('user_id', $user->getKey())
            ->value('role');

        return $role instanceof HouseholdRole ? $role : HouseholdRole::tryFrom((string) $role);
    }

    public function hasMember(?User $user): bool
    {
        return $this->roleOf($user) !== null;
    }

    public function isAdministeredBy(?User $user): bool
    {
        return $this->roleOf($user) === HouseholdRole::Admin;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeWithMember(Builder $query, ?User $user): void
    {
        $query->whereHas('users', fn (Builder $member) => $member->whereKey($user?->getKey()));
    }
}
