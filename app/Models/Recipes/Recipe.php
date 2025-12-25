<?php

namespace App\Models\Recipes;

use App\Models\FilterScope;
use App\Models\SlugifyTrait;
use Spatie\Image\Manipulations;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recipe extends Model
{
    use FilterScope,
        SoftDeletes,
        SlugifyTrait,
        HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
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

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'media',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'preparation_time' => 'datetime:H:i',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
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
     * The relations that should cascade on delete
     *
     * @var array
     */
    protected $softCascade = [
        'ingredients',
        'ingredientGroups',
        'ratings',
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
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('isAdminOrOwnOrPublic', function (Builder $query) {
            if (auth()->check() && auth()->user()->admin) {
                return $query;
            }

            return $query->where(function (Builder $q) {
                /** @var Recipe $q */
                return $q->isOwn();
            })->orWhere(function (Builder $q) {
                /** @var Recipe $q */
                return $q->isPublic();
            });
        });
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'instructions'      => $this->instructions,
            'preparation_time'  => $this->preparation_time,
            'category_id'       => $this->category_id,
        ];
    }

    /**
     * Modify the query used to retrieve models when making all of the models searchable.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with([
            'author',
            'category',
            'cookbook',
            'tags',
            'ingredients',
            'ingredientGroups',
            'ratings',
        ]);
    }

    /**
     * Register new media conversions
     *
     * This method is used by the  spatie/laravel-medialibrary package
     *
     * @param \Spatie\MediaLibrary\MediaCollections\Models\Media|null $media
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(200)
            ->height(200)
            ->sharpen(10);

        $this->addMediaConversion('webp')
            ->format(Manipulations::FORMAT_WEBP);
    }

    /**
     * Get only the recipes of the logged in user
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsOwn(Builder $builder): Builder
    {
        return $builder->whereUserId(auth()->id());
    }

    /**
     * Get only the "public" recipes
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsPublic(Builder $builder): Builder
    {
        return $builder->whereNull('cookbook_id');
    }

    /**
     * Get complexity as translated text
     *
     * @return string
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
     *
     * @return int|null
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
     *
     * @return void
     */
    public function setPreparationTimeAttribute(string $time = null): void
    {
        if ($time === '00:00') {
            $time = null;
        }

        $this->attributes['preparation_time'] = $time;
    }

    /**
     * Get the related photos
     *
     * @return \Illuminate\Support\Collection
     */
    public function getPhotosAttribute(): Collection
    {
        return $this->getMedia('recipe_photos')->map(function (Media $media) {
            return collect([
                'id'            => $media->id,
                'name'          => $media->name,
                'url'           => $media->getUrl(),
                'conversions'   => $media->getGeneratedConversions(),
            ]);
        });
    }

    /**
     * Evaluate if the user can edit this recipe
     *
     * @return bool
     */
    public function getCanEditAttribute(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        return auth()->user()->can('update', $this);
    }

    /**
     * Get the ratings count
     *
     * @return int
     */
    public function getRatingsCountAttribute(): int
    {
        return $this->ratings->count();
    }

    /**
     * Get the all given stars
     *
     * @return int
     */
    public function getStarsAttribute(): int
    {
        return $this->ratings->sum('stars');
    }

    /**
     * Get the average of stars
     *
     * @return float
     */
    public function getStarsAverageAttribute(): float
    {
        if (!$this->ratings_count) {
            return 0;
        }

        return $this->stars / $this->ratings_count;
    }

    /**
     * Get the recipe's author
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo('\App\Models\Author');
    }

    /**
     * Get the recipe's category
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo('\App\Models\Recipes\Category');
    }

    /**
     * Get the recipe's cookbook
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cookbook(): BelongsTo
    {
        return $this->belongsTo('\App\Models\Recipes\Cookbook');
    }

    /**
     * Get the recipe's tags
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany('\App\Models\Recipes\Tag');
    }

    /**
     * Get the recipe's ingredients
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany('\App\Models\Ingredients\Ingredient');
    }

    /**
     * Get the recipe's ingredientGroups
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ingredientGroups(): HasMany
    {
        return $this->hasMany('\App\Models\Ingredients\IngredientGroup');
    }

    /**
     * Get the recipe's ratings
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ratings(): HasMany
    {
        return $this->hasMany('\App\Models\Ratings\Rating');
    }
}
