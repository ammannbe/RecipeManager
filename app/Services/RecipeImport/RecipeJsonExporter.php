<?php

namespace App\Services\RecipeImport;

use App\Models\Ingredient;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Services\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Produces the same JSON shape the importer consumes, so a recipe can be round-tripped.
 */
class RecipeJsonExporter
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Recipe $recipe, bool $withPhotos = false): array
    {
        $recipe->loadMissing([
            'author',
            'category',
            'cookbook',
            'tags',
            'ingredientGroups.ingredients.food',
            'ingredientGroups.ingredients.unit',
            'ingredientGroups.ingredients.ingredientAttributes',
            'ingredientGroups.ingredients.ingredients',
            'ungroupedIngredients.food',
            'ungroupedIngredients.unit',
            'ungroupedIngredients.ingredientAttributes',
            'ungroupedIngredients.ingredients',
        ]);

        return [
            'name' => $recipe->name,
            'category' => $recipe->category?->name,
            'author' => $recipe->author?->name,
            'cookbook' => $recipe->cookbook?->name,
            'servings' => $recipe->servings !== null ? (int) $recipe->servings : null,
            'serving_type' => $recipe->serving_type,
            'complexity' => $recipe->complexity->value,
            'preparation_time' => $recipe->preparation_time?->format('H:i'),
            'instructions' => $recipe->instructions,
            'tags' => $recipe->tags->pluck('name')->values()->all(),
            'ingredients' => $recipe->ungroupedIngredients
                ->reject(fn (Ingredient $ingredient): bool => $ingredient->ingredient_id !== null)
                ->map(fn (Ingredient $ingredient): array => $this->ingredient($ingredient))
                ->values()
                ->all(),
            'ingredient_groups' => $recipe->ingredientGroups
                ->map(fn (IngredientGroup $group): array => [
                    'name' => $group->name,
                    'ingredients' => $group->ingredients
                        ->reject(fn (Ingredient $ingredient): bool => $ingredient->ingredient_id !== null)
                        ->map(fn (Ingredient $ingredient): array => $this->ingredient($ingredient))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'photos' => $withPhotos ? $this->photos($recipe) : [],
        ];
    }

    public function toJson(Recipe $recipe, bool $withPhotos = false): string
    {
        return json_encode(
            $this->toArray($recipe, $withPhotos),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{}';
    }

    public function filename(Recipe $recipe): string
    {
        return Str::slug($recipe->name).'.json';
    }

    /**
     * @return array<string, mixed>
     */
    private function ingredient(Ingredient $ingredient, bool $withAlternatives = true): array
    {
        $data = [
            'amount' => $ingredient->amount !== null ? (float) $ingredient->amount : null,
            'amount_max' => $ingredient->amount_max !== null ? (float) $ingredient->amount_max : null,
            'unit' => $ingredient->unit?->name,
            'food' => $ingredient->food?->name,
            'attributes' => $ingredient->ingredientAttributes->pluck('name')->values()->all(),
        ];

        if ($withAlternatives) {
            $data['alternatives'] = $ingredient->ingredients
                ->map(fn (Ingredient $alternative): array => $this->ingredient($alternative, withAlternatives: false))
                ->values()
                ->all();
        }

        return $data;
    }

    /**
     * @return array<int, array{filename: string, data: string}>
     */
    private function photos(Recipe $recipe): array
    {
        $photos = [];

        foreach ($recipe->photos as $document) {
            /** @var Document $document */
            $path = $recipe->getKey().'/'.$document->name();

            if (! Storage::disk('recipes')->exists($path)) {
                continue;
            }

            $binary = Storage::disk('recipes')->get($path);

            if ($binary === null) {
                continue;
            }

            $photos[] = [
                'filename' => $document->name(),
                'data' => 'data:'.Storage::disk('recipes')->mimeType($path).';base64,'.base64_encode($binary),
            ];
        }

        return $photos;
    }
}
