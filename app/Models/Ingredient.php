<?php

namespace App\Models;

use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
     * Generate a human-readable name (e.g. 200 - 300 g Ananas (fresh, diced))
     *
     * @return Attribute<string, never>
     */
    public function name(): Attribute
    {
        return Attribute::make(
            get: fn () => collect([
                collect([$this->amount, $this->amount_max])
                    ->filter()
                    ->implode(' - '),

                $this->unit?->name,
                $this->food?->name,

                $this->ingredientAttributes->isNotEmpty()
                    ? '('.$this->ingredientAttributes->pluck('name')->implode(', ').')'
                    : null,
            ])
                ->filter()
                ->implode(' ')
        );
    }

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
     * @return BelongsTo<Ingredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<IngredientGroup, $this>
     */
    public function group(): BelongsTo
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
