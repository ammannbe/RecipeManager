<?php

namespace App\Http\Controllers;

use App\Enums\Complexity;
use App\Models\Category;
use App\Models\Recipe;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $quick = $request->boolean('quick');
        $complexity = (string) $request->string('complexity');
        $category = $request->integer('category');
        $selectedSort = (string) $request->string('sort', 'created_at_desc');

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
            ->with(['author', 'category', 'cookbook'])
            ->withCount('ratings')
            ->withAvg('ratings', 'stars')
            ->where(function (Builder $query): void {
                $query->whereNull('cookbook_id');

                if (user()) {
                    $query->orWhere('author_id', user()->author_id);
                }
            })
            ->search(['name', 'instructions'], $search)
            ->when($quick, fn (Builder $query): Builder => $query->where('preparation_time', '<=', '00:30:00'))
            ->when(
                in_array($complexity, $complexityValues, true),
                fn (Builder $query): Builder => $query->where('complexity', $complexity),
            )
            ->when($category > 0, fn (Builder $query): Builder => $query->where('category_id', $category))
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
            'search' => $search,
            'quick' => $quick,
            'complexity' => $complexity,
            'selectedCategory' => $category > 0 ? $category : null,
            'selectedSort' => $selectedSort,
            'paginationPages' => $paginationPages,
            'currentPage' => $currentPage,
        ]);
    }

    public function show(Recipe $recipe): View
    {
        abort_unless($this->canView($recipe), 404);

        $recipe->load([
            'author',
            'category',
            'cookbook',
            'ingredients',
            'ingredients.ingredients',
            'ingredients.ingredientGroup',
            'ingredients.food',
            'ingredients.unit',
            'ingredientGroups',
        ]);

        return view('recipes.show', [
            'recipe' => $recipe,
        ]);
    }

    private function canView(Recipe $recipe): bool
    {
        if (! $recipe->cookbook_id) {
            return true;
        }

        return user()?->author_id === $recipe->author_id;
    }
}
