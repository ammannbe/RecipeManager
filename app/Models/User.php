<?php

namespace App\Models;

use Carbon\Carbon;
use App\Notifications\Users\VerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable,
        SoftDeletes,
        OwnerTrait,
        HasFactory,
        TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'email_verified_at',
        'password',
        'remember_token',
        'author',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'admin' => 'boolean',
        'password' => 'hashed',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'name',
        'has_verified_email',
    ];

    /**
     * Relations that cascade or restrict on delete.
     *
     * @var array
     */
    protected $softCascade = [
        'recipes'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * Get the name attribute
     *
     * @return string
     */
    public function getNameAttribute(): string
    {
        return $this->author->name ?? '';
    }

    /**
     * Check if the user has a verified email
     *
     * @return bool
     */
    public function getHasVerifiedEmailAttribute(): bool
    {
        return $this->hasVerifiedEmail();
    }

    /**
     * Get the user's recipes
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function recipes(): HasMany
    {
        return $this->hasMany('\App\Models\Recipe');
    }

    /**
     * Get the user's cookbooks
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cookbooks(): HasMany
    {
        return $this->hasMany('\App\Models\Cookbook');
    }

    /**
     * Get the user's author
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function author(): HasOne
    {
        return $this->hasOne('\App\Models\Author');
    }

    public function initials(): string
    {
        return \Str::of($this->name)->upper()->substr(0, 2);
    }
}
