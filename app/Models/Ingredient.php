<?php

namespace App\Models;

use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'amount',
        'amount_max',
        'unit_id',
        'food_id',
        'ingredient_group_id',
        'ingredient_id',
        'position',
    ];

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Food, $this>
     */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    /**
     * Get the "original" ingredient
     *
     * @return BelongsTo<Ingredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * Get all alternatives to this ingredient
     *
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<IngredientGroup, $this>
     */
    public function ingredientGroup(): BelongsTo
    {
        return $this->belongsTo(IngredientGroup::class);
    }

    /**
     * @return BelongsToMany<IngredientAttribute, $this>
     */
    public function ingredientAttributes(): BelongsToMany
    {
        return $this->belongsToMany(IngredientAttribute::class);
    }
}
