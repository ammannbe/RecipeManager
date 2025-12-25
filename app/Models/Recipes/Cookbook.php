<?php

namespace App\Models\Recipes;

use App\Models\SlugifyTrait;
use App\Models\OrderByNameScope;
use App\Models\AdminOrOwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cookbook extends Model
{
    use SoftDeletes, SlugifyTrait, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        // Only for CookbookFactory
        'user_id',
        'author_id',
    ];

    /**
     * Relations that cascade or restrict on delete.
     *
     * @var array
     */
    protected $softCascade = [
        'recipes'
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new AdminOrOwnerScope);
        static::addGlobalScope(new OrderByNameScope);
    }

    /**
     * Get the category's recipes
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function recipes(): HasMany
    {
        return $this->hasMany('\App\Models\Recipes\Recipe');
    }
}
