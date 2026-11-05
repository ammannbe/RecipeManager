<?php

namespace App\Observers;

use App\Models\Ingredient;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Services\RecipeNames;
use Illuminate\Support\Facades\Storage;

class RecipeObserver
{
    /**
     * Ingredients and groups go to the trash with the recipe, at its deletion time and with their
     * positions, so restoring brings them back as they were. A force delete leaves them to the
     * foreign keys, which cascade to ingredients, groups, attribute and tag pivots.
     */
    public function deleted(Recipe $recipe): void
    {
        if ($recipe->isForceDeleting()) {
            return;
        }

        $recipe->ingredients()->update(['deleted_at' => $recipe->deleted_at]);
        $recipe->ingredientGroups()->update(['deleted_at' => $recipe->deleted_at]);
    }

    /**
     * Refused while a live recipe uses the name (only possible without cookbook, see RecipeNames).
     */
    public function restoring(Recipe $recipe): ?bool
    {
        if (app(RecipeNames::class)->liveDuplicateOf($recipe) !== null) {
            return false;
        }

        if ($recipe->deleted_at === null) {
            return null;
        }

        // Older trashes cascaded through model events, a moment before the recipe's own timestamp.
        $window = [$recipe->deleted_at->copy()->subSeconds(5), $recipe->deleted_at];

        Ingredient::onlyTrashed()->where('recipe_id', $recipe->id)->whereBetween('deleted_at', $window)->update(['deleted_at' => null]);
        IngredientGroup::onlyTrashed()->where('recipe_id', $recipe->id)->whereBetween('deleted_at', $window)->update(['deleted_at' => null]);

        return null;
    }

    /**
     * Photos live in the recipe's own directory on the private disk.
     */
    public function forceDeleted(Recipe $recipe): void
    {
        Storage::disk('recipes')->deleteDirectory((string) $recipe->id);
    }
}
