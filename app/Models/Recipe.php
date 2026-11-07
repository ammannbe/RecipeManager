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
        'source',
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
     * Visible to everyone, either on its own or through a published cookbook.
     * The record-level counterpart of the is_public half of scopeVisibleTo().
     */
    public function isPublished(): bool
    {
        return $this->is_public || (bool) $this->cookbook?->is_public;
    }

    /**
     * The single source of truth for who may see a recipe. RecipePolicy::view()
     * and isPublished() mirror this for individual records.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        if ($user?->isAdmin()) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('recipes.is_public', true)
                ->orWhereHas('cookbook', fn (Builder $cookbook) => $cookbook->where('is_public', true));

            if ($user?->author_id) {
                $query->orWhere('recipes.author_id', $user->author_id);
            }

            if ($user !== null) {
                // Shared personally or with one of the user's households.
                $query->orWhereHas(
                    'cookbook',
                    fn (Builder $cookbook) => $cookbook->sharedWith($user, ['can_read', 'can_admin']),
                );
            }
        });
    }

    /**
     * Seasonal: at least one food is in season (fresh) and at least half of the foods with
     * season data are. Foods without season data (salt, flour …) are ignored.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInSeason(Builder $query, int $month): void
    {
        $inSeason = 'count(distinct case when json_contains(foods.season_months, ?) then foods.id end)';

        $query->whereIn(
            'recipes.id',
            Ingredient::query()
                ->select('ingredients.recipe_id')
                ->join('foods', 'foods.id', '=', 'ingredients.food_id')
                ->whereNull('foods.deleted_at')
                ->whereNotNull('foods.season_months')
                ->groupBy('ingredients.recipe_id')
                ->havingRaw($inSeason.' >= 1', [(string) $month])
                ->havingRaw('2 * '.$inSeason.' >= count(distinct foods.id)', [(string) $month]),
        );
    }

    /**
     * Same rule as scopeInSeason(), from the counts selected with seasonFoodsCount()
     * (`season_foods_count` for the month, `season_data_foods_count`); false if not selected.
     */
    public function isSeasonal(): bool
    {
        $inSeason = (int) ($this->getAttribute('season_foods_count') ?? 0);
        $withData = (int) ($this->getAttribute('season_data_foods_count') ?? 0);

        return $inSeason >= 1 && 2 * $inSeason >= $withData;
    }

    /**
     * Correlated subquery (outer table recipes): distinct foods of the recipe with season data,
     * or only those in season in the given month. Alternatives count like their parents.
     *
     * @return Builder<Ingredient>
     */
    public static function seasonFoodsCount(?int $month = null): Builder
    {
        return Ingredient::query()
            ->selectRaw('count(distinct foods.id)')
            ->join('foods', 'foods.id', '=', 'ingredients.food_id')
            ->whereColumn('ingredients.recipe_id', 'recipes.id')
            ->whereNull('foods.deleted_at')
            ->whereNotNull('foods.season_months')
            ->when(
                $month !== null,
                fn (Builder $query): Builder => $query->whereRaw('json_contains(foods.season_months, ?)', [(string) $month]),
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
