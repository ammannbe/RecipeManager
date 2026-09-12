<?php

namespace App\Models;

use App\Casts\AsDocuments;
use App\Enums\Complexity;
use App\Traits\Searchable;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'author_id',
        'cookbook_id',
        'category_id',
        'name',
        'is_public',
        'servings',
        'serving_type',
        'complexity',
        'instructions',
        'preparation_time',
        'photos',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'servings' => 'float',
        'complexity' => Complexity::class,
        'preparation_time' => 'datetime:H:i',
        'photos' => AsDocuments::class.':recipes',
    ];

    /**
     * The single source of truth for who may see a recipe. RecipePolicy::view()
     * mirrors this for individual records.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        if ($user?->admin) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('recipes.is_public', true);

            if ($user?->author_id) {
                $query->orWhere('recipes.author_id', $user->author_id);
            }
        });
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
     * Unfiltered on purpose: the delete cascade has to reach alternatives too.
     *
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class)->orderBy('position');
    }

    /**
     * Top-level ingredients without a group; alternatives hang off their parent instead.
     *
     * @return HasMany<Ingredient, $this>
     */
    public function ungroupedIngredients(): HasMany
    {
        return $this->hasMany(Ingredient::class)
            ->whereNull('ingredient_group_id')
            ->whereNull('ingredient_id')
            ->orderBy('position');
    }

    /**
     * @return HasMany<IngredientGroup, $this>
     */
    public function ingredientGroups(): HasMany
    {
        return $this->hasMany(IngredientGroup::class)->orderBy('position');
    }
}
