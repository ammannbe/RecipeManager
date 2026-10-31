<?php

namespace App\Http\Controllers;

use App\Enums\Complexity;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\Tag;
use App\Services\Document;
use App\Services\Season;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RecipeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $quick = $request->boolean('quick');
        $seasonal = $request->boolean('seasonal');
        $complexity = (string) $request->string('complexity');
        $category = $request->integer('category');
        $cookbook = $request->integer('cookbook');
        $selectedSort = (string) $request->string('sort', 'created_at_desc');

        $selectedTags = Tag::query()
            ->whereIn('id', array_map('intval', (array) $request->input('tags', [])))
            ->pluck('id')
            ->all();

        $sortOptions = [
            'created_at_desc' => ['created_at', 'desc'],
            'created_at_asc' => ['created_at', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'complexity_asc' => ['complexity', 'asc'],
            'complexity_desc' => ['complexity', 'desc'],
        ];

        if (! array_key_exists($selectedSort, $sortOptions)) {
            $selectedSort = 'created_at_desc';
        }

        [$sortBy, $sortDirection] = $sortOptions[$selectedSort];

        $complexityValues = array_map(
            static fn (Complexity $value): string => $value->value,
            Complexity::cases(),
        );

        $recipes = Recipe::query()
            ->with(['author', 'category', 'cookbook', 'tags'])
            ->visibleTo(user())
            ->search(['name', 'instructions'], $search)
            ->when($quick, fn (Builder $query): Builder => $query->where('preparation_time', '<=', '00:30:00'))
            ->when($seasonal, fn (Builder $query): Builder => $query->inSeason(Season::currentMonth()))
            ->when(
                in_array($complexity, $complexityValues, true),
                fn (Builder $query): Builder => $query->where('complexity', $complexity),
            )
            ->when($category > 0, fn (Builder $query): Builder => $query->where('category_id', $category))
            ->when($cookbook > 0, fn (Builder $query): Builder => $query->where('cookbook_id', $cookbook))
            ->when(
                $selectedTags !== [],
                fn (Builder $query): Builder => $query->whereHas(
                    'tags',
                    fn (Builder $tags): Builder => $tags->whereIn('tags.id', $selectedTags),
                ),
            )
            ->orderBy($sortBy, $sortDirection)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $currentPage = $recipes->currentPage();
        $lastPage = $recipes->lastPage();
        $rawPages = collect([1, $currentPage - 2, $currentPage - 1, $currentPage, $currentPage + 1, $currentPage + 2, $lastPage])
            ->filter(static fn (int $page): bool => $page >= 1 && $page <= $lastPage)
            ->unique()
            ->sort()
            ->values();

        $paginationPages = [];
        $previousPage = null;

        foreach ($rawPages as $page) {
            if ($previousPage !== null && $page - $previousPage > 1) {
                $paginationPages[] = null;
            }

            $paginationPages[] = $page;
            $previousPage = $page;
        }

        return view('recipes.index', [
            'recipes' => $recipes,
            'categories' => Category::query()->orderBy('name')->get(),
            'tags' => Tag::query()->visibleTo($request->user())->orderBy('name')->get(),
            'cookbooks' => Cookbook::query()->visibleTo(user())->orderBy('name')->get(),
            'search' => $search,
            'quick' => $quick,
            'seasonal' => $seasonal,
            'complexity' => $complexity,
            'selectedCategory' => $category > 0 ? $category : null,
            'selectedCookbook' => $cookbook > 0 ? $cookbook : null,
            'selectedTags' => $selectedTags,
            'selectedSort' => $selectedSort,
            'paginationPages' => $paginationPages,
            'currentPage' => $currentPage,
        ]);
    }

    public function show(Recipe $recipe): View
    {
        // 404 rather than 403 so a private recipe's existence is not leaked.
        abort_unless(user()?->can('view', $recipe) ?? $recipe->isPublished(), 404);

        $recipe->load([
            'author',
            'category',
            'cookbook',
            'tags',
            'ingredients',
            'ingredients.ingredientGroup',
            'ingredients.food',
            'ingredients.unit',
            'ingredients.ingredientAttributes',
            'ingredients.ingredients.food',
            'ingredients.ingredients.unit',
            'ingredients.ingredients.ingredientAttributes',
            'ingredientGroups',
        ]);

        $month = Season::currentMonth();
        $seasonFoods = $recipe->ingredients
            ->pluck('food')
            ->filter(fn (?Food $food): bool => $food?->hasSeasonData() ?? false)
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('recipes.show', [
            'recipe' => $recipe,
            'seasonFoods' => $seasonFoods,
            'inSeasonFoods' => $seasonFoods->filter(fn (Food $food): bool => $food->inSeason($month))->values(),
        ]);
    }

    /**
     * Seasonal calendar: foods in season in a month and visible recipes using most of them.
     */
    public function season(Request $request): View
    {
        $month = Season::month($request->query('month'));

        $foods = Food::query()
            ->visibleTo(user())
            ->where(fn (Builder $query): Builder => $query->whereNotNull('season_months')->orWhereNotNull('storage_months'))
            ->orderBy('name')
            ->get();

        $recipes = Recipe::query()
            ->with(['author', 'category', 'cookbook', 'tags'])
            ->visibleTo(user())
            ->addSelect([
                'season_foods_count' => Recipe::seasonFoodsCount($month),
                'season_data_foods_count' => Recipe::seasonFoodsCount(),
            ])
            ->having('season_foods_count', '>=', 1)
            ->orderByDesc('season_foods_count')
            ->orderBy('season_data_foods_count')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(24)
            ->get();

        return view('recipes.season', [
            'month' => $month,
            'months' => Season::monthNames(),
            'freshFoods' => $foods->filter(fn (Food $food): bool => $food->inSeason($month))->values(),
            'storageFoods' => $foods
                ->filter(fn (Food $food): bool => ! $food->inSeason($month) && in_array($month, $food->storage_months ?? [], true))
                ->values(),
            'yearFoods' => $foods,
            'recipes' => $recipes,
        ]);
    }

    /**
     * Serves a photo from the private disk, guarded by the same rule as the recipe itself.
     */
    public function photo(Recipe $recipe, string $filename): BinaryFileResponse
    {
        abort_unless(user()?->can('view', $recipe) ?? $recipe->isPublished(), 404);

        // Only filenames the recipe actually stores; blocks traversal and guessing.
        $document = $recipe->photos->first(
            fn (Document $photo): bool => $photo->name() === $filename
        );

        abort_if($document === null, 404);

        return $document->response() ?? abort(404);
    }
}
