<?php

namespace App\Models;

use App\Traits\Searchable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Author extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

    /**
     * @return Attribute<string, never>
     */
    public function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => \Str::slug($this->name),
        );
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
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
