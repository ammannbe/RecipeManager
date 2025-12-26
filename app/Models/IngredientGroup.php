<?php

namespace App\Models;

use App\Models\SlugifyTrait;
use App\Models\OrderByNameScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientGroup extends Model
{
    use SoftDeletes, SlugifyTrait, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Relations that cascade or restrict on delete.
     *
     * @var array
     */
    protected $softCascade = [
        'ingredients',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new OrderByNameScope);
    }

    /**
     * Get the ingredient-group's recipe
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo('\App\Models\Recipe');
    }

    /**
     * Get the ingredient-group's ingredients
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany('\App\Models\Ingredient');
    }
}
