<?php

namespace App\Models;

use Database\Factories\RatingCriterionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RatingCriterion extends Model
{
    /** @use HasFactory<RatingCriterionFactory> */
    use HasFactory;

    use SoftDeletes;

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
     * @return HasMany<Rating, $this>
     */
    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }
}
