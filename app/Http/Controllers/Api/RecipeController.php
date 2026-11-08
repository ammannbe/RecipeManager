<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRecipesRequest;
use App\Http\Requests\Api\ShowRecipeRequest;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Api\RecipePresenter;
use Illuminate\Http\JsonResponse;

class RecipeController extends Controller
{
    public function __construct(
        private readonly RecipePresenter $presenter,
    ) {}

    /**
     * Visible recipes using any of the given foods, best matches first, then (with
     * season_month) more in-season foods. Grouped ingredients and alternatives count
     * too: every ingredient row carries recipe_id.
     */
    public function search(SearchRecipesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $foodIds = $request->foodIds();
        $seasonMonth = $request->seasonMonth();

        $query = $request->nameQuery();
        $like = $query === null ? null : '%'.addcslashes($query, '\\%_').'%';

        $recipes = Recipe::query()
            ->visibleTo($user)
            ->when($like !== null, fn ($builder) => $builder->where('recipes.name', 'like', $like))
            ->when($foodIds !== [], fn ($builder) => $builder
                ->whereHas('ingredients', fn ($ingredients) => $ingredients->whereIn('food_id', $foodIds))
                ->addSelect([
                    'matched_foods_count' => Ingredient::query()
                        ->selectRaw('count(distinct food_id)')
                        ->whereColumn('ingredients.recipe_id', 'recipes.id')
                        ->whereIn('food_id', $foodIds),
                ])
                ->orderByDesc('matched_foods_count'))
            // Names starting with the query first.
            ->when($query !== null, fn ($builder) => $builder->orderByRaw('case when recipes.name like ? then 0 else 1 end', [addcslashes($query, '\\%_').'%']))
            ->when($seasonMonth !== null, fn ($builder) => $builder
                ->addSelect(['season_foods_count' => Recipe::seasonFoodsCount($seasonMonth)])
                ->orderByDesc('season_foods_count'))
            ->orderBy('name')
            ->orderBy('id')
            ->limit($request->limit())
            ->get();

        /** @var array<int, array<int, int>> $recipeFoods */
        $recipeFoods = Ingredient::query()
            ->whereIn('recipe_id', $recipes->modelKeys())
            ->distinct()
            ->orderBy('food_id')
            ->get(['recipe_id', 'food_id'])
            ->groupBy('recipe_id')
            ->map(fn ($ingredients): array => $ingredients->pluck('food_id')->map(fn ($id): int => (int) $id)->all())
            ->all();

        return response()->json([
            'data' => $recipes->map(function (Recipe $recipe) use ($recipeFoods, $foodIds): array {
                $all = $recipeFoods[$recipe->id] ?? [];

                return $this->presenter->summary($recipe, $all, array_values(array_intersect($all, $foodIds)));
            })->all(),
        ]);
    }

    public function show(ShowRecipeRequest $request, Recipe $recipe): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->presenter->detail(
                $recipe,
                $request->servings(),
                $user->locale ?? app()->getLocale(),
            ),
        ]);
    }
}
