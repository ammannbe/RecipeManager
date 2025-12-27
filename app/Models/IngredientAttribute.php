<?php

namespace App\Models;

use Database\Factories\IngredientAttributeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngredientAttribute extends Model
{
    /** @use HasFactory<IngredientAttributeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

    /**
     * @return BelongsToMany<Ingredient, $this>
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class);
    }
}
