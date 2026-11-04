<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Average weight of one piece unit of a food, e.g. 1 Stück Eier = 60 g.
 *
 * @property float $grams
 */
class FoodUnitWeight extends Model
{
    protected $fillable = [
        'food_id',
        'unit_id',
        'grams',
    ];

    protected $casts = [
        'grams' => 'float',
    ];

    /**
     * @return BelongsTo<Food, $this>
     */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
