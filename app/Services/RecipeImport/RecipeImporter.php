<?php

namespace App\Services\RecipeImport;

use App\Models\Cookbook;
use App\Models\Ingredient;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeNames;
use App\Services\RecipePhotos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RecipeImporter
{
    /** @var array<string, int|null> */
    private array $lookup = [];

    public function __construct(
        private readonly RelationResolver $resolver,
        private readonly ImageDownloader $downloader,
        private readonly RecipeNames $names,
        private readonly RecipePhotos $photos,
    ) {}

    /**
     * @param  array<string, int|null>  $decisions  lookup key => chosen model id, or null to omit
     * @param  int|null  $cookbookId  explicit cookbook chosen in the import wizard, overrides the JSON "cookbook" field
     * @param  bool|null  $isPublic  explicit visibility chosen in the import wizard, defaults to public unless the cookbook is private
     * @param  bool  $renameDuplicate  save a duplicate name as "Name (2)" … instead of refusing it
     *
     * @throws ValidationException
     */
    public function import(
        ParsedRecipe $parsed,
        ?User $user,
        array $decisions = [],
        ?int $cookbookId = null,
        ?bool $isPublic = null,
        bool $renameDuplicate = false,
    ): Recipe {
        $report = $this->resolver->resolve($parsed, $user);

        $this->lookup = $report->resolved;

        foreach ($report->unresolved as $unresolved) {
            $key = $unresolved->key();

            if (! array_key_exists($key, $decisions)) {
                if ($unresolved->type->isOptional() || ! $this->isUsed($parsed, $unresolved, $decisions)) {
                    $this->lookup[$key] = null;

                    continue;
                }

                throw ValidationException::withMessages([
                    'json' => __('Unresolved :type ":value".', [
                        'type' => $unresolved->type->label(),
                        'value' => $unresolved->value,
                    ]),
                ]);
            }

            if ($decisions[$key] === null && ! $unresolved->type->isOmittable()) {
                throw ValidationException::withMessages([
                    'json' => __('Unresolved :type ":value".', [
                        'type' => $unresolved->type->label(),
                        'value' => $unresolved->value,
                    ]),
                ]);
            }

            $this->lookup[$key] = $decisions[$key];
        }

        if (! $this->hasIngredients($parsed)) {
            throw ValidationException::withMessages([
                'json' => __('The recipe needs at least one ingredient that is not omitted.'),
            ]);
        }

        $resolvedCookbookId = $cookbookId ?? ($parsed->cookbook !== null
            ? $this->requiredId(LookupType::Cookbook, $parsed->cookbook)
            : null);
        $authorId = $this->authorId($parsed, $user);

        $parsed = $this->guardDuplicate($parsed, $resolvedCookbookId, $authorId, $renameDuplicate);

        return DB::transaction(function () use ($parsed, $resolvedCookbookId, $authorId, $isPublic): Recipe {
            /** @var Cookbook|null $resolvedCookbook */
            $resolvedCookbook = Cookbook::query()->find($resolvedCookbookId);

            $recipe = new Recipe;
            $recipe->fill([
                'name' => $parsed->name,
                'category_id' => $this->requiredId(LookupType::Category, $parsed->category),
                'cookbook_id' => $resolvedCookbookId,
                'is_public' => $isPublic ?? ($resolvedCookbook === null || $resolvedCookbook->is_public),
                'servings' => $parsed->servings,
                'serving_type' => $parsed->servingType,
                'complexity' => $parsed->complexity,
                'preparation_time' => $parsed->preparationTime,
                // The recipe page renders instructions as HTML, so never let raw HTML through.
                'instructions' => Str::markdown($parsed->instructions, [
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                ]),
            ]);

            $recipe->setAttribute('author_id', $authorId);
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
            return $this->requiredId(LookupType::Author, $parsed->author);
        }

        return (int) $user?->author_id;
    }

    /**
     * Checked with the cookbook and author the recipe is saved with, trashed recipes included.
     *
     * @throws ValidationException
     */
    private function guardDuplicate(ParsedRecipe $parsed, ?int $cookbookId, int $authorId, bool $rename): ParsedRecipe
    {
        $duplicate = $this->names->findDuplicate($parsed->name, $cookbookId, $authorId);

        if ($duplicate === null) {
            return $parsed;
        }

        if ($rename) {
            return $parsed->withName($this->names->availableName($parsed->name, $cookbookId, $authorId));
        }

        throw ValidationException::withMessages([
            'json' => $this->names->duplicateMessage($parsed->name, $duplicate),
        ]);
    }

    private function syncTags(Recipe $recipe, ParsedRecipe $parsed): void
    {
        $ids = array_filter(array_map(
            fn (string $tag): ?int => $this->id(LookupType::Tag, $tag),
            $parsed->tags
        ), fn (?int $id): bool => $id !== null);

        $recipe->tags()->sync(array_unique($ids));
    }

    /**
     * Ingredients whose food was omitted are left out (with their alternatives), and so is
     * a group left without ingredients.
     */
    private function createIngredients(Recipe $recipe, ParsedRecipe $parsed): void
    {
        $position = 1;

        foreach ($this->kept($parsed->ingredients) as $ingredient) {
            $this->createIngredient($recipe, $ingredient, null, $position++);
        }

        $groupPosition = 1;

        foreach ($parsed->groups as $group) {
            $ingredients = $this->kept($group->ingredients);

            if ($ingredients === []) {
                continue;
            }

            /** @var IngredientGroup $model */
            $model = $recipe->ingredientGroups()->create([
                'name' => $group->name,
                'position' => $groupPosition++,
            ]);

            $position = 1;

            foreach ($ingredients as $ingredient) {
                $this->createIngredient($recipe, $ingredient, $model, $position++);
            }
        }
    }

    /**
     * @param  array<int, ParsedIngredient>  $ingredients
     * @return array<int, ParsedIngredient>
     */
    private function kept(array $ingredients): array
    {
        return array_values(array_filter(
            $ingredients,
            fn (ParsedIngredient $ingredient): bool => $this->id(LookupType::Food, $ingredient->food) !== null,
        ));
    }

    private function hasIngredients(ParsedRecipe $parsed): bool
    {
        if ($this->kept($parsed->ingredients) !== []) {
            return true;
        }

        foreach ($parsed->groups as $group) {
            if ($this->kept($group->ingredients) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a unit or attribute is still needed: false when it only appears on ingredients
     * (or alternatives) whose food is omitted.
     *
     * @param  array<string, int|null>  $decisions
     */
    private function isUsed(ParsedRecipe $parsed, UnresolvedValue $value, array $decisions): bool
    {
        if (! in_array($value->type, [LookupType::Unit, LookupType::Attribute], true)) {
            return true;
        }

        $omitted = fn (string $food): bool => array_key_exists($key = ResolutionReport::key(LookupType::Food, $food), $decisions) && $decisions[$key] === null;

        foreach ($parsed->allIngredients() as $ingredient) {
            $uses = $value->type === LookupType::Unit
                ? $ingredient->unit !== null && mb_strtolower($ingredient->unit) === mb_strtolower($value->value)
                : in_array(mb_strtolower($value->value), array_map('mb_strtolower', $ingredient->attributes), true);

            if ($uses && ! $omitted($ingredient->food)) {
                return true;
            }
        }

        return false;
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
            'food_id' => $this->requiredId(LookupType::Food, $parsed->food),
            'ingredient_group_id' => $group?->getKey(),
            'position' => $position,
        ]);

        $this->syncAttributes($ingredient, $parsed);

        // Alternatives share the parent's position slot; the unique index separates them
        // by ingredient_id and IngredientObserver copies over recipe and group.
        $alternativePosition = 1;

        foreach ($this->kept($parsed->alternatives) as $alternative) {
            /** @var Ingredient $child */
            $child = $ingredient->ingredients()->create([
                'amount' => $alternative->amount,
                'amount_max' => $alternative->amountMax,
                'unit_id' => $alternative->unit !== null ? $this->id(LookupType::Unit, $alternative->unit) : null,
                'food_id' => $this->requiredId(LookupType::Food, $alternative->food),
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

        $ids = array_filter(array_map(
            fn (string $attribute): ?int => $this->id(LookupType::Attribute, $attribute),
            $parsed->attributes
        ), fn (?int $id): bool => $id !== null);

        $ingredient->ingredientAttributes()->sync(array_unique($ids));
    }

    private function storePhotos(Recipe $recipe, ParsedRecipe $parsed): void
    {
        if ($parsed->photos === []) {
            return;
        }

        $stored = [];

        foreach ($parsed->photos as $index => $photo) {
            try {
                $decoded = $photo['data'] !== null
                    ? $this->photos->decode($photo['data'])
                    : $this->downloader->download((string) $photo['url']);
            } catch (InvalidArgumentException) {
                $decoded = null;
            }

            if ($decoded === null) {
                continue;
            }

            [$binary, $extension] = $decoded;

            $filename = Str::slug(
                pathinfo($photo['filename'], PATHINFO_FILENAME) ?: $recipe->name.'-'.($index + 1)
            ).'.'.$extension;

            Storage::disk('recipes')->put($recipe->getKey().'/'.$filename, $binary);

            $stored[] = [
                'path' => $filename,
                'source' => $photo['source'],
                'is_ai_generated' => $photo['is_ai_generated'],
            ];
        }

        if ($stored !== []) {
            $recipe->photos = $stored;
            $recipe->save();
        }
    }

    private function requiredId(LookupType $type, string $value): int
    {
        $id = $this->id($type, $value);

        if ($id === null) {
            throw ValidationException::withMessages([
                'json' => __('Unresolved :type ":value".', [
                    'type' => $type->label(),
                    'value' => $value,
                ]),
            ]);
        }

        return $id;
    }

    /**
     * Returns null when an optional lookup was deliberately left unassigned.
     */
    private function id(LookupType $type, string $value): ?int
    {
        $key = ResolutionReport::key($type, $value);

        if (! array_key_exists($key, $this->lookup)) {
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
