<?php

namespace App\Services\Api;

use App\Enums\Complexity;
use App\Models\Category;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves a recipe idea sent by a companion app: private, outside any cookbook and
 * written by the user's author, like a recipe a user creates in Filament.
 */
class RecipeIdeaCreator
{
    /** @var array<string, int> */
    private array $foods = [];

    /** @var array<string, int> */
    private array $units = [];

    /**
     * @param  array{name: string, servings?: int|null, instructions: string, ingredients: array<int, array{amount?: float|int|string|null, amount_max?: float|int|string|null, unit?: string|null, food: string}>}  $data
     */
    public function create(User $user, array $data): Recipe
    {
        return DB::transaction(function () use ($user, $data): Recipe {
            $recipe = new Recipe;
            $recipe->fill([
                'name' => trim($data['name']),
                'cookbook_id' => null,
                'category_id' => $this->categoryId(),
                'is_public' => false,
                'servings' => $data['servings'] ?? null,
                'complexity' => Complexity::Normal,
                // The recipe page renders instructions as HTML, so never let raw HTML through.
                'instructions' => Str::markdown($data['instructions'], [
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                ]),
            ]);
            // Registration gives every user an author (users.author_id is not nullable).
            $recipe->author_id = $user->author_id;
            $recipe->save();

            $position = 1;

            foreach ($data['ingredients'] as $ingredient) {
                $unit = trim((string) ($ingredient['unit'] ?? ''));

                $recipe->ingredients()->create([
                    'amount' => $this->amount($ingredient['amount'] ?? null),
                    'amount_max' => $this->amount($ingredient['amount_max'] ?? null),
                    'unit_id' => $unit !== '' ? $this->unitId($unit) : null,
                    'food_id' => $this->foodId(trim($ingredient['food'])),
                    'position' => $position++,
                ]);
            }

            return $recipe;
        });
    }

    /**
     * Recipes need a category; ideas land in a configurable default one.
     */
    private function categoryId(): int
    {
        return $this->firstOrRestore(
            Category::withTrashed(),
            ['name'],
            (string) config('services.shoppinglist.default_category'),
            fn (string $name): Category => Category::query()->create(['name' => $name]),
        );
    }

    /**
     * Name lookups rely on the case-insensitive column collation, the same one that
     * enforces the unique name index, so a match here can never collide on insert.
     */
    private function foodId(string $name): int
    {
        return $this->foods[mb_strtolower($name)] ??= $this->firstOrRestore(
            Food::withTrashed(),
            ['name'],
            $name,
            fn (string $name): Food => Food::query()->create(['name' => $name]),
        );
    }

    /**
     * Units match on any of their spellings, with or without a trailing dot,
     * so "g" finds "Gramm" with the shortcut "g" and "Stk." finds the shortcut "Stk".
     */
    private function unitId(string $name): int
    {
        $bare = rtrim($name, '.');

        return $this->units[mb_strtolower($bare)] ??= $this->firstOrRestore(
            Unit::withTrashed(),
            ['name', 'name_shortcut', 'name_plural', 'name_plural_shortcut'],
            $name,
            fn (string $name): Unit => Unit::query()->create(['name' => $name]),
            array_values(array_unique([$name, $bare, $bare.'.'])),
        );
    }

    /**
     * @template TModel of Category|Food|Unit
     *
     * @param  Builder<TModel>  $query  including trashed rows, which still occupy the unique index
     * @param  array<int, string>  $columns
     * @param  callable(string): TModel  $create
     * @param  array<int, string>|null  $spellings  names to look for, defaults to $name
     */
    private function firstOrRestore(Builder $query, array $columns, string $name, callable $create, ?array $spellings = null): int
    {
        $spellings ??= [$name];

        /** @var TModel|null $model */
        $model = $query
            ->where(function (Builder $query) use ($columns, $spellings): void {
                foreach ($columns as $column) {
                    $query->orWhereIn($column, $spellings);
                }
            })
            // Prefer an exact match on the main name, then live rows over trashed ones.
            ->orderByRaw('name = ? desc', [$name])
            ->orderByRaw('deleted_at is null desc')
            ->orderBy('id')
            ->first();

        if ($model === null) {
            return (int) $create($name)->getKey();
        }

        if ($model->trashed()) {
            $model->restore();
        }

        return (int) $model->getKey();
    }

    private function amount(float|int|string|null $amount): ?float
    {
        return $amount === null || $amount === '' ? null : (float) $amount;
    }
}
