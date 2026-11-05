<?php

namespace App\Services;

use App\Models\Recipe;
use App\Services\RecipeImport\RecipeJsonSchema;
use Closure;

/**
 * Recipe names are unique per cookbook, trashed recipes included (unique index on name and
 * cookbook_id). Without a cookbook they are unique per author, which only this code checks:
 * MariaDB lets NULL cookbook ids repeat.
 */
class RecipeNames
{
    public const MAX_LENGTH = RecipeJsonSchema::MAX_NAME;

    /**
     * The recipe already using the name in the cookbook (without cookbook: among the author's
     * recipes without cookbook), by default also in the trash.
     */
    public function findDuplicate(
        string $name,
        ?int $cookbookId,
        ?int $authorId,
        bool $withTrashed = true,
        ?int $ignoreId = null,
    ): ?Recipe {
        return Recipe::query()
            ->withTrashed($withTrashed)
            ->where('name', trim($name))
            ->where('cookbook_id', $cookbookId)
            ->when($cookbookId === null, fn ($query) => $query->where('author_id', $authorId))
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->first();
    }

    /**
     * A live recipe that took the name of a trashed one, which blocks restoring it.
     */
    public function liveDuplicateOf(Recipe $recipe): ?Recipe
    {
        return $this->findDuplicate($recipe->name, $recipe->cookbook_id, $recipe->author_id, withTrashed: false, ignoreId: $recipe->id);
    }

    /**
     * The name itself when it is free, otherwise "Name (2)", "Name (3)" … within the column length.
     */
    public function availableName(string $name, ?int $cookbookId, ?int $authorId, ?int $ignoreId = null): string
    {
        $name = trim($name);

        if ($this->findDuplicate($name, $cookbookId, $authorId, ignoreId: $ignoreId) === null) {
            return $name;
        }

        // "Apfelkuchen (2)" continues with "(3)" instead of becoming "Apfelkuchen (2) (2)".
        $base = (string) preg_replace('/\s*\(\d+\)$/u', '', $name);

        for ($number = 2; ; $number++) {
            $suffix = ' ('.$number.')';
            $candidate = rtrim(mb_substr($base, 0, self::MAX_LENGTH - mb_strlen($suffix))).$suffix;

            if ($this->findDuplicate($candidate, $cookbookId, $authorId, ignoreId: $ignoreId) === null) {
                return $candidate;
            }
        }
    }

    public function duplicateMessage(string $name, Recipe $duplicate): string
    {
        return $duplicate->trashed()
            ? __('A recipe named ":name" already exists in this cookbook (in the trash).', ['name' => trim($name)])
            : __('A recipe named ":name" already exists in this cookbook.', ['name' => trim($name)]);
    }

    /**
     * Validation rule: the name is free in the cookbook, also against the trash.
     *
     * @return Closure(string, mixed, Closure): void
     */
    public function rule(?int $cookbookId, ?int $authorId, ?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($cookbookId, $authorId, $ignoreId): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            $duplicate = $this->findDuplicate($value, $cookbookId, $authorId, ignoreId: $ignoreId);

            if ($duplicate !== null) {
                $fail($this->duplicateMessage($value, $duplicate));
            }
        };
    }
}
