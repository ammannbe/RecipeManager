<?php

namespace App\Models;

use App\Casts\AsDocuments;
use App\Enums\Complexity;
use App\Traits\Searchable;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    use Searchable;
    use SoftDeletes;

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
        'photos' => AsDocuments::class.':recipes',
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
     * @return Attribute<string, never>
     */
    public function stars(): Attribute
    {
        return Attribute::make(
            get: fn () => round((float) $this->ratings()->avg('stars'), 1),
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
    public function ingredientGroups(): HasMany
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
