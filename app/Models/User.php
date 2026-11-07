<?php

namespace App\Models;

use App\Entitlements\Entitlements;
use App\Traits\Searchable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasName, MustVerifyEmail, OAuthenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'email',
        'locale',
        'email_verified_at',
        'password',
        'admin',
        'ai_enabled',
    ];

    protected $hidden = [
        'email_verified_at',
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'ai_enabled' => true,
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'ai_enabled' => 'boolean',
    ];

    protected $with = [
        'author',
    ];

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * Cookbooks shared with this user by someone else.
     *
     * @return BelongsToMany<Cookbook, $this, CookbookMembership>
     */
    public function sharedCookbooks(): BelongsToMany
    {
        return $this->belongsToMany(Cookbook::class, 'cookbook_user')
            ->using(CookbookMembership::class)
            ->withPivot(CookbookMembership::GRANTS)
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Household, $this, HouseholdMembership>
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_user')
            ->using(HouseholdMembership::class)
            ->withPivot(['role', 'joined_at']);
    }

    /**
     * Signs in through the OpenID Connect provider only (see App\Auth\OidcAccount).
     */
    public function isLinkedToOidc(): bool
    {
        return $this->oidc_sub !== null;
    }

    /**
     * Last entitlements snapshot of the AccountManager (linked users only).
     *
     * @return HasOne<UserEntitlement, $this>
     */
    public function entitlement(): HasOne
    {
        return $this->hasOne(UserEntitlement::class);
    }

    /**
     * Admin rights: users.admin, or the AccountManager's admin flag for linked users with
     * AUTH_ADMIN_SOURCE=account-manager (App\Entitlements\Entitlements). Never check users.admin directly.
     */
    public function isAdmin(): bool
    {
        return Entitlements::isAdmin($this);
    }

    /**
     * Admins in queries, same rules as isAdmin().
     *
     * @param  Builder<User>  $query
     */
    public function scopeAdmins(Builder $query): void
    {
        if (! Entitlements::adminFromAccountManager()) {
            $query->where('admin', true);

            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $local) => $local->whereNull('oidc_sub')->where('admin', true))
            ->orWhere(fn (Builder $linked) => $linked->whereNotNull('oidc_sub')->whereHas('entitlement', fn (Builder $entitlement) => $entitlement
                ->where('status', Entitlements::ACTIVE)
                ->where('admin', true))));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getFilamentName(): string
    {
        $name = (string) $this->author->name;

        return $name !== '' ? $name : (string) ($this->email ?? 'User');
    }

    /**
     * @return HasMany<AiUsage, $this>
     */
    public function aiUsages(): HasMany
    {
        return $this->hasMany(AiUsage::class);
    }
}
