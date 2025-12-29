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
use Lamansky\Fraction\Fraction;

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
            get: function () {
                $amount = Fraction::fromFloat((float) $this->amount)->toUnicodeString();
                $amount_max = Fraction::fromFloat((float) $this->amount_max)->toUnicodeString();
                $amountString = collect([$amount, $amount_max])->filter()->implode(' - ');

                $unit = $this->unit?->getMatchingName($this->amount_max ?? $this->amount);

                $ingredientAttributes = $this->ingredientAttributes->isNotEmpty()
                    ? '('.$this->ingredientAttributes->pluck('name')->implode(', ').')'
                    : null;

                $values = [
                    $amountString,
                    $unit,
                    $this->food?->name,
                    $ingredientAttributes,
                ];

                return collect($values)
                    ->filter()
                    ->implode(' ');
            },
        );
    }

    public function getAmountAndUnit(float $multiply = 1): string
    {
        $amount = Fraction::fromFloat((float) $this->amount * $multiply)->toUnicodeString();
        $amount_max = Fraction::fromFloat((float) $this->amount_max * $multiply)->toUnicodeString();

        $amountString = collect([$amount, $amount_max])
            ->filter()
            ->implode(' - ');

        $values = [
            $amountString,
            $this->unit?->getMatchingName($this->amount_max ?? $this->amount),
        ];

        return collect($values)
            ->filter()
            ->implode(' ');
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
