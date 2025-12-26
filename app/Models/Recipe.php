<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Image\Manipulations;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Recipe extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cookbook_id',
        'category_id',
        'name',
        'servings',
        'serving_type',
        'complexity',
        'instructions',
        'preparation_time',
    ];

    protected $casts = [
        'preparation_time' => 'datetime:H:i',
    ];

    protected $appends = [
        'photos',
        'can_edit',
        'stars',
        'stars_average',
        'ratings_count',
        'complexity_text',
        'complexity_number',
    ];

    /**
     * Possible values for the complexity_types enum field
     *
     * @var array
     */
    public const COMPLEXITY_TYPES = [
        'simple',
        'normal',
        'difficult',
    ];

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
     * Register new media conversions
     *
     * This method is used by the  spatie/laravel-medialibrary package
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(200)
            ->height(200)
            ->sharpen(10);

        $this->addMediaConversion('webp')
            ->format(Manipulations::FORMAT_WEBP);
    }

    /**
     * Get complexity as translated text
     */
    public function getComplexityTextAttribute(): string
    {
        $text = '';

        switch ($this->attributes['complexity']) {
            case 'simple':
                $text = __('Simple');
                break;

            case 'normal':
                $text = __('Normal');
                break;

            case 'difficult':
                $text = __('Difficult');
                break;
        }

        /** @var string */
        return $text;
    }

    /**
     * Get the complexity as number
     *
     * 0 = simple;
     * 1 = simple;
     * 2 = simple;
     */
    public function getComplexityNumberAttribute(): ?int
    {
        switch ($this->attributes['complexity']) {
            case 'simple':
                return 0;

            case 'normal':
                return 1;

            case 'difficult':
                return 2;
        }

        return null;
    }

    /**
     * Set the preparation time
     */
    public function setPreparationTimeAttribute(?string $time = null): void
    {
        if ($time === '00:00') {
            $time = null;
        }

        $this->attributes['preparation_time'] = $time;
    }

    /**
     * Get the related photos
     */
    public function getPhotosAttribute(): Collection
    {
        return $this->getMedia('recipe_photos')->map(function (Media $media) {
            return collect([
                'id' => $media->id,
                'name' => $media->name,
                'url' => $media->getUrl(),
                'conversions' => $media->getGeneratedConversions(),
            ]);
        });
    }

    /**
     * Evaluate if the user can edit this recipe
     */
    public function getCanEditAttribute(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return auth()->user()->can('update', $this);
    }

    /**
     * Get the ratings count
     */
    public function getRatingsCountAttribute(): int
    {
        return $this->ratings->count();
    }

    /**
     * Get the all given stars
     */
    public function getStarsAttribute(): int
    {
        return $this->ratings->sum('stars');
    }

    /**
     * Get the average of stars
     */
    public function getStarsAverageAttribute(): float
    {
        if (! $this->ratings_count) {
            return 0;
        }

        return $this->stars / $this->ratings_count;
    }

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Cookbook, $this>
     */
    public function cookbook(): BelongsTo
    {
        return $this->belongsTo(Cookbook::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    /**
     * @return HasMany<IngredientGroup, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(IngredientGroup::class);
    }

    /**
     * @return HasMany<Rating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }
}
