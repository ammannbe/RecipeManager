<?php

namespace App\Http\Controllers;

use App\Enums\Complexity;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Services\Document;
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
        $complexity = (string) $request->string('complexity');
        $category = $request->integer('category');
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
            ->when(
                in_array($complexity, $complexityValues, true),
                fn (Builder $query): Builder => $query->where('complexity', $complexity),
            )
            ->when($category > 0, fn (Builder $query): Builder => $query->where('category_id', $category))
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
            'tags' => Tag::query()->orderBy('name')->get(),
            'search' => $search,
            'quick' => $quick,
            'complexity' => $complexity,
            'selectedCategory' => $category > 0 ? $category : null,
            'selectedTags' => $selectedTags,
            'selectedSort' => $selectedSort,
            'paginationPages' => $paginationPages,
            'currentPage' => $currentPage,
        ]);
    }

    public function show(Recipe $recipe): View
    {
        // 404 rather than 403 so a private recipe's existence is not leaked.
        abort_unless(user()?->can('view', $recipe) ?? $recipe->is_public, 404);

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

        return view('recipes.show', [
            'recipe' => $recipe,
        ]);
    }

    /**
     * Serves a photo from the private disk, guarded by the same rule as the recipe itself.
     */
    public function photo(Recipe $recipe, string $filename): BinaryFileResponse
    {
        abort_unless(user()?->can('view', $recipe) ?? $recipe->is_public, 404);

        // Only filenames the recipe actually stores; blocks traversal and guessing.
        $document = $recipe->photos->first(
            fn (Document $photo): bool => $photo->name() === $filename
        );

        abort_if($document === null, 404);

        return $document->response() ?? abort(404);
    }
}
