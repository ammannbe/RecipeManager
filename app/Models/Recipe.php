<?php

namespace App\Models;

use App\Enums\Complexity;
use App\Services\Document;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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
        'complexity' => Complexity::class,
        'preparation_time' => 'datetime:H:i',
    ];

    protected $withCount = [
        'ratings',
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
     * @return Attribute<float, never>
     */
    public function stars(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->ratings_count
                ? $this->ratings()->sum('stars') / $this->ratings_count
                : 0,
        );
    }

    /**
     * @return Attribute<Collection, never>
     */
    public function photos(): Attribute
    {
        return Attribute::make(
            get: fn () => collect(\Storage::disk('recipes')->files($this->id))
                ->map(fn ($file) => new Document($file, 'recipes')),
        );
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
