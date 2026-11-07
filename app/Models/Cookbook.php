<?php

namespace App\Models;

use App\Households\KontoHouseholds;
use App\Jobs\ReportKontoShares;
use Database\Factories\CookbookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Cookbook extends Model
{
    /** @use HasFactory<CookbookFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'author_id',
        'name',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope('author_name', function (Builder $builder) {
            $builder->withAggregate('author', 'name');
        });

        // The AccountManager shows the names of the cookbooks shared with a household (HOUSEHOLDS_DRIVER=konto).
        static::updated(function (Cookbook $cookbook): void {
            if ($cookbook->wasChanged('name')) {
                $cookbook->reportKontoShares();
            }
        });
        static::deleted(fn (Cookbook $cookbook) => $cookbook->reportKontoShares());
        static::restored(fn (Cookbook $cookbook) => $cookbook->reportKontoShares());
    }

    public function reportKontoShares(): void
    {
        if (KontoHouseholds::enabled()) {
            ReportKontoShares::dispatchFor($this->households()->whereNotNull('konto_household_uuid')->get());
        }
    }

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * @return HasMany<Recipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * @return BelongsToMany<User, $this, CookbookMembership>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cookbook_user')
            ->using(CookbookMembership::class)
            ->withPivot(CookbookMembership::GRANTS)
            ->withTimestamps();
    }

    /**
     * Households the cookbook is shared with; their grants apply to every member.
     *
     * @return BelongsToMany<Household, $this, CookbookHouseholdShare>
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'cookbook_household')
            ->using(CookbookHouseholdShare::class)
            ->withPivot(CookbookHouseholdShare::GRANTS)
            ->withTimestamps();
    }

    /**
     * @return HasMany<CookbookInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CookbookInvitation::class);
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $user->author_id === $this->author_id;
    }

    /**
     * Whether the user holds a grant on this cookbook. The owner and global admins
     * hold every grant implicitly.
     */
    public function grants(?User $user, string $grant): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->admin || $this->isOwnedBy($user)) {
            return true;
        }

        // A cookbook admin implicitly holds the record-level grants too.
        return $this->sharesFor($user)->contains(
            fn (CookbookMembership|CookbookHouseholdShare $share): bool => (bool) ($share->can_admin || $share->{$grant}),
        );
    }

    /**
     * Every share that applies to the user: the personal membership and the shares with
     * households the user belongs to. Grants add up.
     *
     * @return Collection<int, CookbookMembership|CookbookHouseholdShare>
     */
    public function sharesFor(User $user): Collection
    {
        /** @var Collection<int, CookbookMembership|CookbookHouseholdShare> $shares */
        $shares = CookbookHouseholdShare::query()
            ->where('cookbook_id', $this->getKey())
            ->whereIn('household_id', HouseholdMembership::query()->select('household_id')->where('user_id', $user->getKey()))
            ->get()
            ->toBase();

        $membership = $this->membershipFor($user);

        return $membership === null ? $shares : $shares->prepend($membership);
    }

    public function membershipFor(?User $user): ?CookbookMembership
    {
        if ($user === null) {
            return null;
        }

        /** @var CookbookMembership|null $membership */
        $membership = $this->members()
            ->wherePivot('user_id', $user->getKey())
            ->first()?->getRelationValue('pivot');

        return $membership;
    }

    /**
     * Public cookbooks, plus the user's own and any shared with them (personally or through
     * a household) with can_read/can_admin. Global admins see every cookbook. Mirrors grants() as a query scope.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        if ($user?->admin) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('is_public', true);

            if ($user?->author_id) {
                $query->orWhere('author_id', $user->author_id);
            }

            if ($user !== null) {
                $query->orWhere(fn (Builder $shared) => $shared->sharedWith($user, ['can_read', 'can_admin']));
            }
        });
    }

    /**
     * Cookbooks the user owns or has a can_admin share on (personally or through a household). Global admins see every
     * cookbook. Mirrors the record-level grants() check but as a query scope.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAdministeredBy(Builder $query, ?User $user): void
    {
        if ($user?->admin) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('author_id', $user?->author_id);

            if ($user !== null) {
                $query->orWhere(fn (Builder $shared) => $shared->sharedWith($user, ['can_admin']));
            }
        });
    }

    /**
     * Cookbooks shared with the user, personally (cookbook_user) or with a household the
     * user belongs to (cookbook_household). With grants, the share must hold at least one
     * of them; without, any share counts. The query-side counterpart of sharesFor().
     *
     * @param  Builder<$this>  $query
     * @param  list<string>  $grants
     */
    public function scopeSharedWith(Builder $query, User $user, array $grants = []): void
    {
        $withGrants = function (Builder $share, string $table) use ($grants): void {
            if ($grants !== []) {
                $share->where(function (Builder $query) use ($grants, $table): void {
                    foreach ($grants as $grant) {
                        $query->orWhere($table.'.'.$grant, true);
                    }
                });
            }
        };

        $query->where(fn (Builder $query) => $query
            ->whereHas('members', function (Builder $member) use ($user, $withGrants): void {
                $member->whereKey($user->getKey());
                $withGrants($member, 'cookbook_user');
            })
            ->orWhereHas('households', function (Builder $household) use ($user, $withGrants): void {
                $household->whereHas('users', fn (Builder $member) => $member->whereKey($user->getKey()));
                $withGrants($household, 'cookbook_household');
            }));
    }
}
