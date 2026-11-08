<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\User;
use App\Services\RecipeImport\RecipeJsonValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecipeDraftController extends Controller
{
    /**
     * Stores recipe JSON in the import format and returns the import wizard URL. Only the format
     * is checked here; foods, units, categories etc. are matched or created by the user there.
     */
    public function store(Request $request, RecipeJsonValidator $validator): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Photos would make drafts large; they can be added on the recipe afterwards.
        $request->validate(['photos' => ['prohibited']]);

        try {
            $validator->validate((string) json_encode($request->all()));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['recipe' => $exception->validator->errors()->all()]);
        }

        RecipeDraft::prune();

        $draft = RecipeDraft::query()->create([
            'user_id' => $user->id,
            'data' => $request->all(),
            'expires_at' => now()->addHours(RecipeDraft::LIFETIME_HOURS),
        ]);

        return response()->json([
            'data' => [
                'id' => $draft->id,
                // Plain route: no Filament panel is booted in API requests.
                'url' => route('filament.app.pages.import-recipe', ['draft' => $draft->id]),
                'expires_at' => $draft->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * State of an own draft: pending, imported (with the recipe, null when it is gone or no longer
     * visible) or expired. Drafts of others and unknown ones are 404, like pruned ones.
     */
    public function show(Request $request, string $draft): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $found = RecipeDraft::query()->whereKey($draft)->where('user_id', $user->id)->first();
        abort_if($found === null, 404);

        $recipe = $found->recipe_id === null ? null : Recipe::query()->visibleTo($user)->whereKey($found->recipe_id)->first();

        return response()->json([
            'data' => [
                'id' => $found->id,
                'status' => $found->status(),
                'expires_at' => $found->expires_at->toIso8601String(),
                'imported_at' => $found->imported_at?->toIso8601String(),
                // Like the recipes of the search (RecipePresenter::summary).
                'recipe' => $recipe === null ? null : [
                    'id' => $recipe->id,
                    'name' => $recipe->name,
                    'url' => route('recipes.show', $recipe),
                ],
            ],
        ]);
    }
}
