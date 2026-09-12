<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

    /**
     * @return BelongsToMany<Recipe, $this>
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class);
    }
}
