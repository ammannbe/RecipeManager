<?php

namespace App\Services\RecipeImport;

use App\Models\Ingredient;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecipeImporter
{
    /** @var array<string, int> */
    private array $lookup = [];

    public function __construct(private readonly RelationResolver $resolver) {}

    /**
     * @param  array<string, int>  $decisions  lookup key => chosen model id
     *
     * @throws ValidationException
     */
    public function import(ParsedRecipe $parsed, ?User $user, array $decisions = []): Recipe
    {
        $report = $this->resolver->resolve($parsed, $user);

        $this->lookup = $report->resolved;

        foreach ($report->unresolved as $unresolved) {
            $key = $unresolved->key();

            if (! array_key_exists($key, $decisions)) {
                throw ValidationException::withMessages([
                    'json' => __('Unresolved :type ":value".', [
                        'type' => $unresolved->type->label(),
                        'value' => $unresolved->value,
                    ]),
                ]);
            }

            $this->lookup[$key] = $decisions[$key];
        }

        $this->guardDuplicate($parsed, $user);

        return DB::transaction(function () use ($parsed, $user): Recipe {
            $recipe = new Recipe;
            $recipe->fill([
                'name' => $parsed->name,
                'category_id' => $this->id(LookupType::Category, $parsed->category),
                'cookbook_id' => $parsed->cookbook !== null
                    ? $this->id(LookupType::Cookbook, $parsed->cookbook)
                    : null,
                'servings' => $parsed->servings,
                'serving_type' => $parsed->servingType,
                'complexity' => $parsed->complexity,
                'preparation_time' => $parsed->preparationTime,
                'instructions' => Str::markdown($parsed->instructions),
            ]);

            $recipe->setAttribute('author_id', $this->authorId($parsed, $user));
            $recipe->save();

            $this->syncTags($recipe, $parsed);
            $this->createIngredients($recipe, $parsed);
            $this->storePhotos($recipe, $parsed);

            return $recipe;
        });
    }

    private function authorId(ParsedRecipe $parsed, ?User $user): int
    {
        if ($user?->admin && $parsed->author !== null) {
            return $this->id(LookupType::Author, $parsed->author);
        }

        return (int) $user?->author_id;
    }

    /**
     * @throws ValidationException
     */
    private function guardDuplicate(ParsedRecipe $parsed, ?User $user): void
    {
        $cookbookId = $parsed->cookbook !== null
            ? $this->id(LookupType::Cookbook, $parsed->cookbook)
            : null;

        $exists = Recipe::withTrashed()
            ->where('name', $parsed->name)
            ->where('cookbook_id', $cookbookId)
            ->when($cookbookId === null, fn ($query) => $query->where('author_id', $this->authorId($parsed, $user)))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'json' => __('A recipe named ":name" already exists in this cookbook.', [
                    'name' => $parsed->name,
                ]),
            ]);
        }
    }

    private function syncTags(Recipe $recipe, ParsedRecipe $parsed): void
    {
        $ids = array_map(
            fn (string $tag): int => $this->id(LookupType::Tag, $tag),
            $parsed->tags
        );

        $recipe->tags()->sync(array_unique($ids));
    }

    private function createIngredients(Recipe $recipe, ParsedRecipe $parsed): void
    {
        $position = 1;

        foreach ($parsed->ingredients as $ingredient) {
            $this->createIngredient($recipe, $ingredient, null, $position++);
        }

        $groupPosition = 1;

        foreach ($parsed->groups as $group) {
            /** @var IngredientGroup $model */
            $model = $recipe->ingredientGroups()->create([
                'name' => $group->name,
                'position' => $groupPosition++,
            ]);

            $position = 1;

            foreach ($group->ingredients as $ingredient) {
                $this->createIngredient($recipe, $ingredient, $model, $position++);
            }
        }
    }

    private function createIngredient(
        Recipe $recipe,
        ParsedIngredient $parsed,
        ?IngredientGroup $group,
        int $position,
    ): void {
        /** @var Ingredient $ingredient */
        $ingredient = $recipe->ingredients()->create([
            'amount' => $parsed->amount,
            'amount_max' => $parsed->amountMax,
            'unit_id' => $parsed->unit !== null ? $this->id(LookupType::Unit, $parsed->unit) : null,
            'food_id' => $this->id(LookupType::Food, $parsed->food),
            'ingredient_group_id' => $group?->getKey(),
            'position' => $position,
        ]);

        $this->syncAttributes($ingredient, $parsed);

        // Alternatives share the parent's position slot; the unique index separates them
        // by ingredient_id and IngredientObserver copies over recipe and group.
        $alternativePosition = 1;

        foreach ($parsed->alternatives as $alternative) {
            /** @var Ingredient $child */
            $child = $ingredient->ingredients()->create([
                'amount' => $alternative->amount,
                'amount_max' => $alternative->amountMax,
                'unit_id' => $alternative->unit !== null ? $this->id(LookupType::Unit, $alternative->unit) : null,
                'food_id' => $this->id(LookupType::Food, $alternative->food),
                'position' => $alternativePosition++,
            ]);

            $this->syncAttributes($child, $alternative);
        }
    }

    private function syncAttributes(Ingredient $ingredient, ParsedIngredient $parsed): void
    {
        if ($parsed->attributes === []) {
            return;
        }

        $ids = array_map(
            fn (string $attribute): int => $this->id(LookupType::Attribute, $attribute),
            $parsed->attributes
        );

        $ingredient->ingredientAttributes()->sync(array_unique($ids));
    }

    private function storePhotos(Recipe $recipe, ParsedRecipe $parsed): void
    {
        if ($parsed->photos === []) {
            return;
        }

        $stored = [];

        foreach ($parsed->photos as $index => $photo) {
            $decoded = $this->decodePhoto($photo['data']);

            if ($decoded === null) {
                continue;
            }

            [$binary, $extension] = $decoded;

            $filename = Str::slug(
                pathinfo($photo['filename'], PATHINFO_FILENAME) ?: $recipe->name.'-'.($index + 1)
            ).'.'.$extension;

            Storage::disk('recipes')->put($recipe->getKey().'/'.$filename, $binary);

            $stored[] = $filename;
        }

        if ($stored !== []) {
            $recipe->photos = $stored;
            $recipe->save();
        }
    }

    /**
     * Decodes a base64 data URI, rejecting anything that is not an allowed image.
     *
     * @return array{0: string, 1: string}|null
     */
    private function decodePhoto(string $data): ?array
    {
        if (! preg_match('#^data:([a-z/+.-]+);base64,#i', $data, $matches)) {
            return null;
        }

        if (! in_array(strtolower($matches[1]), RecipeJsonSchema::ALLOWED_PHOTO_MIMES, true)) {
            return null;
        }

        $binary = base64_decode(substr($data, strlen($matches[0])), true);

        if ($binary === false || strlen($binary) > RecipeJsonSchema::MAX_PHOTO_BYTES) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);

        if (! is_string($mime) || ! in_array($mime, RecipeJsonSchema::ALLOWED_PHOTO_MIMES, true)) {
            return null;
        }

        return [$binary, match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        }];
    }

    private function id(LookupType $type, string $value): int
    {
        $key = ResolutionReport::key($type, $value);

        if (! isset($this->lookup[$key])) {
            throw ValidationException::withMessages([
                'json' => __('Unresolved :type ":value".', [
                    'type' => $type->label(),
                    'value' => $value,
                ]),
            ]);
        }

        return $this->lookup[$key];
    }
}
