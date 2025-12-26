<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'email',
        'password',
    ];

    protected $hidden = [
        'email_verified_at',
        'password',
        'remember_token',
    ];

    protected $with = [
        'author',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // protected $softCascade = [
    //     'recipes'
    // ];

    /**
     * @return Attribute<string, never>
     */
    public function name(): Attribute
    {
        return Attribute::make(
            get: fn () => \Str::slug($this->author->name),
        );
    }

    /**
     * @return HasOne<Author, $this>
     */
    public function author(): HasOne
    {
        return $this->hasOne(Author::class);
    }

    /**
     * @return HasMany<Recipe, $this>
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * @return HasMany<Cookbook, $this>
     */
    public function cookbooks(): HasMany
    {
        return $this->hasMany(Cookbook::class);
    }
}
