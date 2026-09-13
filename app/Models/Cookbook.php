<?php

namespace App\Models;

use Database\Factories\CookbookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

        $membership = $this->membershipFor($user);

        if ($membership === null) {
            return false;
        }

        // A cookbook admin implicitly holds the record-level grants too.
        return (bool) ($membership->can_admin || $membership->{$grant});
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
}
