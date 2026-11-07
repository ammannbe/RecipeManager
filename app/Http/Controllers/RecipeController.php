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
use App\Services\UnitConverter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

        /** @var array<int, string> $selectedTagNames */
        $selectedTagNames = Tag::query()
            ->whereIn('id', array_map('intval', (array) $request->input('tags', [])))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int|string $id): array => [(int) $id => $name])
            ->all();
        $selectedTags = array_keys($selectedTagNames);

        $sortOptions = [
            'created_at_desc' => ['created_at', 'desc'],
            'created_at_asc' => ['created_at', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'complexity_asc' => ['complexity', 'asc'],
            'complexity_desc' => ['complexity', 'desc'],
        ];

        $sortLabels = [
            'created_at_desc' => __('Newest first'),
            'created_at_asc' => __('Oldest first'),
            'name_asc' => __('Name A-Z'),
            'name_desc' => __('Name Z-A'),
            'complexity_asc' => __('Difficulty low-high'),
            'complexity_desc' => __('Difficulty high-low'),
        ];

        if (! array_key_exists($selectedSort, $sortOptions)) {
            $selectedSort = 'created_at_desc';
        }

        [$sortBy, $sortDirection] = $sortOptions[$selectedSort];

        $complexityValues = array_map(
            static fn (Complexity $value): string => $value->value,
            Complexity::cases(),
        );

        if (! in_array($complexity, $complexityValues, true)) {
            $complexity = '';
        }

        $recipes = Recipe::query()
            ->with(['author', 'category', 'cookbook', 'tags'])
            ->visibleTo(user())
            // For the "Seasonal" badge of the cards (Recipe::isSeasonal()).
            ->addSelect([
                'season_foods_count' => Recipe::seasonFoodsCount(Season::currentMonth()),
                'season_data_foods_count' => Recipe::seasonFoodsCount(),
            ])
            ->search(['name', 'instructions'], $search)
            ->when($quick, fn (Builder $query): Builder => $query->where('preparation_time', '<=', '00:30:00'))
            ->when($seasonal, fn (Builder $query): Builder => $query->inSeason(Season::currentMonth()))
            ->when($complexity !== '', fn (Builder $query): Builder => $query->where('complexity', $complexity))
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

        $categories = Category::query()->orderBy('name')->get();
        $cookbooks = Cookbook::query()->visibleTo(user())->orderBy('name')->get();

        // Current filters as removable chips, each linking to the same URL without it.
        $query = array_filter([
            'search' => $search,
            'quick' => $quick ? 1 : null,
            'seasonal' => $seasonal ? 1 : null,
            'complexity' => $complexity,
            'category' => $category > 0 ? $category : null,
            'cookbook' => $cookbook > 0 ? $cookbook : null,
            'sort' => $selectedSort !== 'created_at_desc' ? $selectedSort : null,
            'tags' => $selectedTags,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $without = static fn (string $key): string => route('recipes.index', Arr::except($query, $key));
        $activeFilters = [];

        if ($search !== '') {
            $activeFilters[] = ['key' => 'search', 'label' => '«'.$search.'»', 'url' => $without('search')];
        }

        if ($quick) {
            $activeFilters[] = ['key' => 'quick', 'label' => __('Max. 30 min'), 'url' => $without('quick')];
        }

        if ($seasonal) {
            $activeFilters[] = ['key' => 'seasonal', 'label' => __('Seasonal'), 'url' => $without('seasonal')];
        }

        if ($complexity !== '') {
            $activeFilters[] = ['key' => 'complexity', 'label' => Complexity::from($complexity)->label(), 'url' => $without('complexity')];
        }

        if ($category > 0) {
            $activeFilters[] = ['key' => 'category', 'label' => $categories->firstWhere('id', $category)->name ?? __('Category'), 'url' => $without('category')];
        }

        if ($cookbook > 0) {
            $activeFilters[] = ['key' => 'cookbook', 'label' => $cookbooks->firstWhere('id', $cookbook)->name ?? __('Cookbook'), 'url' => $without('cookbook')];
        }

        foreach ($selectedTagNames as $tagId => $tagName) {
            $activeFilters[] = [
                'key' => 'tags',
                'label' => $tagName,
                'url' => route('recipes.index', [...Arr::except($query, 'tags'), 'tags' => array_values(array_diff($selectedTags, [$tagId]))]),
            ];
        }

        if ($selectedSort !== 'created_at_desc') {
            $activeFilters[] = ['key' => 'sort', 'label' => $sortLabels[$selectedSort], 'url' => $without('sort')];
        }

        return view('recipes.index', [
            'recipes' => $recipes,
            'categories' => $categories,
            'tags' => Tag::query()->visibleTo($request->user())->orderBy('name')->get(),
            'cookbooks' => $cookbooks,
            'search' => $search,
            'quick' => $quick,
            'seasonal' => $seasonal,
            'complexity' => $complexity,
            'selectedCategory' => $category > 0 ? $category : null,
            'selectedCookbook' => $cookbook > 0 ? $cookbook : null,
            'selectedTags' => $selectedTags,
            'selectedSort' => $selectedSort,
            'sortLabels' => $sortLabels,
            'activeFilters' => $activeFilters,
            'paginationPages' => $paginationPages,
            'currentPage' => $currentPage,
        ]);
    }

    public function show(Recipe $recipe, UnitConverter $converter): View
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
            'ingredients.food.unitWeights',
            'ingredients.unit',
            'ingredients.ingredientAttributes',
            'ingredients.ingredients.food.unitWeights',
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

        $cupSystems = $converter->cupSystems();

        return view('recipes.show', [
            'recipe' => $recipe,
            'seasonFoods' => $seasonFoods,
            'inSeasonFoods' => $seasonFoods->filter(fn (Food $food): bool => $food->inSeason($month))->values(),
            // The mode choice only when a mode changes something (alternatives are in ingredients too).
            'unitModes' => $converter->pageModes($recipe->ingredients, $cupSystems),
            'cupSystems' => $cupSystems,
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

        // Fresh in the month first, then from storage, then the rest; by name within (stable sort).
        $rank = fn (Food $food): int => $food->inSeason($month) ? 0 : (in_array($month, $food->storage_months ?? [], true) ? 1 : 2);

        return view('recipes.season', [
            'month' => $month,
            'months' => Season::monthNames(),
            'foods' => $foods->sortBy($rank)->values(),
            'freshCount' => $foods->filter(fn (Food $food): bool => $food->inSeason($month))->count(),
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
