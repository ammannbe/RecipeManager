<?php

namespace App\Models\Ratings;

use App\Models\FilterScope;
use App\Models\Author;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rating extends Model
{
    use FilterScope, SoftDeletes, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'rating_criterion_id',
        'comment',
        'stars',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'user',
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [
        'ratingCriterion',
        'author',
    ];

    /**
     * Get the stars attribute
     *
     * Check that stars is not NULL
     * and not higher than config('app.max_rating_stars')
     *
     * @return int
     */
    public function getStarsAttribute(): int
    {
        $stars = $this->attributes['stars'];
        $maxStars = config('app.max_rating_stars');

        if (!$stars) {
            return 0;
        }

        if ($stars >= $maxStars) {
            return $maxStars;
        }

        return $stars;
    }

    /**
     * Get the rating's rating-criterion
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ratingCriterion(): BelongsTo
    {
        return $this->belongsTo('\App\Models\Ratings\RatingCriterion');
    }

    /**
     * Get the rating's author
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo('\App\Models\Author');
    }
}
