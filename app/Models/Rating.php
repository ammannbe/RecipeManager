<?php

namespace App\Models;

use Database\Factories\RatingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rating extends Model
{
    /** @use HasFactory<RatingFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'rating_criterion_id',
        'comment',
        'stars',
    ];

    /**
     * @return BelongsTo<RatingCriterion, $this>
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(RatingCriterion::class);
    }

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
