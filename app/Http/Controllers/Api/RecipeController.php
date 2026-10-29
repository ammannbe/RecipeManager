<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRecipesRequest;
use App\Http\Requests\Api\ShowRecipeRequest;
use App\Http\Requests\Api\StoreRecipeRequest;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Api\RecipeIdeaCreator;
use App\Services\Api\RecipePresenter;
use Illuminate\Http\JsonResponse;

class RecipeController extends Controller
{
    public function __construct(
        private readonly RecipePresenter $presenter,
    ) {}

    /**
     * Visible recipes using any of the given foods, best matches first. Grouped
     * ingredients and alternatives count too: every ingredient row carries recipe_id.
     */
    public function search(SearchRecipesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $foodIds = $request->foodIds();

        $recipes = Recipe::query()
            ->visibleTo($user)
            ->whereHas('ingredients', fn ($query) => $query->whereIn('food_id', $foodIds))
            ->addSelect([
                'matched_foods_count' => Ingredient::query()
                    ->selectRaw('count(distinct food_id)')
                    ->whereColumn('ingredients.recipe_id', 'recipes.id')
                    ->whereIn('food_id', $foodIds),
            ])
            ->orderByDesc('matched_foods_count')
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

    public function store(StoreRecipeRequest $request, RecipeIdeaCreator $creator): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name: string, servings?: int|null, instructions: string, ingredients: array<int, array{amount?: float|int|string|null, amount_max?: float|int|string|null, unit?: string|null, food: string}>} $data */
        $data = $request->validated();

        $recipe = $creator->create($user, $data);

        return response()->json([
            'data' => [
                'id' => $recipe->id,
                'url' => route('recipes.show', $recipe),
            ],
        ], 201);
    }
}
