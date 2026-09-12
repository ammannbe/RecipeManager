<?php

namespace App\Models;

use Database\Factories\IngredientGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngredientGroup extends Model
{
    /** @use HasFactory<IngredientGroupFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
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
     * Unfiltered on purpose: the delete cascade has to reach alternatives too.
     *
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class)->orderBy('position');
    }

    /**
     * @return HasMany<Ingredient, $this>
     */
    public function topLevelIngredients(): HasMany
    {
        return $this->hasMany(Ingredient::class)
            ->whereNull('ingredient_id')
            ->orderBy('position');
    }
}
