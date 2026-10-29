<?php

namespace App\Services\Api;

use App\Models\Ingredient;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use Illuminate\Support\Number;

/**
 * Turns recipes into the arrays of the companion app API. Ingredient order, scaling and
 * text follow the recipe page (recipes/show.blade.php and recipeServings in app.js).
 * Photos are deliberately never exposed.
 */
class RecipePresenter
{
    /**
     * @param  array<int, int>  $foodIds  all distinct foods of the recipe
     * @param  array<int, int>  $matchedFoodIds
     * @return array<string, mixed>
     */
    public function summary(Recipe $recipe, array $foodIds, array $matchedFoodIds): array
    {
        return [
            'id' => $recipe->id,
            'name' => $recipe->name,
            'url' => route('recipes.show', $recipe),
            'servings' => $this->number($recipe->servings),
            'serving_type' => $recipe->serving_type,
            'preparation_time' => $recipe->preparation_time?->format('H:i'),
            'matched_food_ids' => $matchedFoodIds,
            'food_ids' => $foodIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Recipe $recipe, ?float $servings, string $locale): array
    {
        $relations = ['food', 'unit', 'ingredientAttributes', 'ingredients.food', 'ingredients.unit', 'ingredients.ingredientAttributes'];

        $recipe->load([
            ...array_map(fn (string $relation): string => 'ungroupedIngredients.'.$relation, $relations),
            ...array_map(fn (string $relation): string => 'ingredientGroups.topLevelIngredients.'.$relation, $relations),
        ]);

        $originalServings = $recipe->servings !== null && $recipe->servings > 0 ? $recipe->servings : null;

        // Like the recipe page: only recipes with servings can be scaled.
        $multiplier = $originalServings !== null && $servings !== null ? $servings / $originalServings : 1.0;

        $ingredients = [];

        foreach ($recipe->ungroupedIngredients as $ingredient) {
            $ingredients[] = $this->ingredient($ingredient, null, $multiplier, $locale);
        }

        foreach ($recipe->ingredientGroups as $group) {
            foreach ($group->topLevelIngredients as $ingredient) {
                $ingredients[] = $this->ingredient($ingredient, $group, $multiplier, $locale);
            }
        }

        return [
            'id' => $recipe->id,
            'name' => $recipe->name,
            'url' => route('recipes.show', $recipe),
            'servings' => $this->number($originalServings !== null ? ($servings ?? $originalServings) : null),
            'original_servings' => $this->number($originalServings),
            'serving_type' => $recipe->serving_type,
            'ingredients' => $ingredients,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ingredient(Ingredient $ingredient, ?IngredientGroup $group, float $multiplier, string $locale, bool $withAlternatives = true): array
    {
        $amount = $ingredient->amount !== null ? round((float) $ingredient->amount * $multiplier, 2) : null;
        $amountMax = $ingredient->amount_max !== null ? round((float) $ingredient->amount_max * $multiplier, 2) : null;
        $unit = $ingredient->unit?->getMatchingName($amountMax ?? $amount);
        $food = (string) $ingredient->food?->name;

        $amountText = implode(' - ', array_map(
            fn (float $value): string => (string) Number::format($value, maxPrecision: 2, locale: $locale),
            array_filter([$amount, $amountMax], fn (?float $value): bool => $value !== null),
        ));

        $text = implode(' ', array_filter([$amountText, $unit, $food], fn (?string $part): bool => $part !== null && $part !== ''));

        $attributes = $ingredient->ingredientAttributes->pluck('name')->implode(', ');

        if ($attributes !== '') {
            $text .= ' ('.$attributes.')';
        }

        $data = [
            'amount' => $this->number($amount),
            'amount_max' => $this->number($amountMax),
            'unit' => $unit,
            'food_id' => $ingredient->food_id,
            'food' => $food,
            'group' => $group?->name,
            'text' => $text,
        ];

        if ($withAlternatives) {
            // "or" alternatives (sub-ingredients via ingredient_id), shown indented on the recipe page.
            $data['alternatives'] = $ingredient->ingredients
                ->map(fn (Ingredient $alternative): array => $this->ingredient($alternative, $group, $multiplier, $locale, false))
                ->values()
                ->all();
        }

        return $data;
    }

    /**
     * Whole numbers as int, so 4.0 is rendered as 4 in JSON.
     */
    private function number(?float $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        return floor($value) === $value ? (int) $value : $value;
    }
}
