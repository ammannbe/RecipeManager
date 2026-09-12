<?php

namespace App\Models;

use Database\Factories\FoodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Food extends Model
{
    /** @use HasFactory<FoodFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'foods';

    protected $fillable = [
        'name',
    ];

    /**
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }
}
