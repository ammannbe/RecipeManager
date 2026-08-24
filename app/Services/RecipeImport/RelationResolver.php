<?php

namespace App\Services\RecipeImport;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class RelationResolver
{
    /** Minimum similarity in percent for a name to be offered as a suggestion. */
    public const SUGGESTION_THRESHOLD = 70;

    /**
     * From this similarity upwards the best suggestion is preselected in the review step.
     * Still a suggestion, not a match: the user has to confirm it.
     */
    public const PRESELECT_THRESHOLD = 85;

    public const MAX_SUGGESTIONS = 3;

    /**
     * Prefetched lookup rows keyed by type, so resolving hundreds of foods stays a single
     * query per table instead of one per ingredient.
     *
     * @var array<string, Collection<int, Model>>
     */
    private array $cache = [];

    public function resolve(ParsedRecipe $recipe, ?User $user): ResolutionReport
    {
        $resolved = [];
        $unresolved = [];
        $contexts = [];

        foreach ($this->collect($recipe, $user) as [$type, $value, $context]) {
            $key = ResolutionReport::key($type, $value);

            if ($context !== null) {
                $contexts[$key][$context] = $context;
            }

            if (isset($resolved[$key]) || isset($unresolved[$key])) {
                continue;
            }

            $match = $this->match($type, $value, $user);

            if ($match !== null) {
                $resolved[$key] = $match;

                continue;
            }

            $unresolved[$key] = new UnresolvedValue(
                type: $type,
                value: $value,
                suggestions: $this->suggest($type, $value, $user),
                canCreate: $type->canBeCreatedBy($user) && mb_strlen($value) <= $type->maxLength(),
            );
        }

        foreach ($unresolved as $key => $value) {
            $unresolved[$key] = $value->withContext(array_values($contexts[$key] ?? []));
        }

        return new ResolutionReport($resolved, array_values($unresolved));
    }

    /**
     * Every lookup value the recipe references, in the order they should be reviewed.
     * The third element names the ingredient a value came from, so the review step can
     * show where an unknown unit or attribute is used.
     *
     * @return array<int, array{0: LookupType, 1: string, 2: string|null}>
     */
    private function collect(ParsedRecipe $recipe, ?User $user): array
    {
        $values = [[LookupType::Category, $recipe->category, null]];

        if ($recipe->cookbook !== null) {
            $values[] = [LookupType::Cookbook, $recipe->cookbook, null];
        }

        if ($recipe->author !== null && $user?->admin) {
            $values[] = [LookupType::Author, $recipe->author, null];
        }

        foreach ($recipe->tags as $tag) {
            $values[] = [LookupType::Tag, $tag, null];
        }

        foreach ($recipe->allIngredients() as $ingredient) {
            $values[] = [LookupType::Food, $ingredient->food, null];

            if ($ingredient->unit !== null) {
                $values[] = [LookupType::Unit, $ingredient->unit, $ingredient->food];
            }

            foreach ($ingredient->attributes as $attribute) {
                $values[] = [LookupType::Attribute, $attribute, $ingredient->food];
            }
        }

        return $values;
    }

    private function match(LookupType $type, string $value, ?User $user): ?int
    {
        $needle = $this->normalise($value);
        $needleForms = $this->numberForms($needle);

        /** @var array<int, Model> $plural */
        $plural = [];

        foreach ($this->rows($type, $user) as $row) {
            foreach ($this->candidateNames($row) as $name) {
                $candidate = $this->normalise($name);

                if ($candidate === $needle) {
                    $this->restore($row);

                    return (int) $row->getKey();
                }

                if (array_intersect($needleForms, $this->numberForms($candidate)) !== []) {
                    $plural[(int) $row->getKey()] = $row;
                }
            }
        }

        // "Ei" vs "Eier" is safe to fold together, but only when exactly one row matches:
        // "Ei" would otherwise just as happily match "Eis".
        if (count($plural) === 1) {
            $row = reset($plural);
            $this->restore($row);

            return (int) $row->getKey();
        }

        return null;
    }

    /**
     * Singular and plural spellings of a German noun that can be derived deterministically.
     * The "s" plural is only stripped, never added, because it would turn "Ei" into "Eis".
     *
     * @return array<int, string>
     */
    private function numberForms(string $value): array
    {
        $forms = [$value];

        foreach (['er', 'en', 'e', 'n', 's'] as $suffix) {
            if (str_ends_with($value, $suffix) && mb_strlen($value) > mb_strlen($suffix) + 1) {
                $forms[] = mb_substr($value, 0, -mb_strlen($suffix));
            }
        }

        foreach (['e', 'en', 'er', 'n'] as $suffix) {
            $forms[] = $value.$suffix;
        }

        return array_values(array_unique($forms));
    }

    /**
     * @return array<int, array{id: int, name: string, score: int}>
     */
    private function suggest(LookupType $type, string $value, ?User $user): array
    {
        $needle = $this->normalise($value);
        $needleForms = $this->numberForms($needle);
        $suggestions = [];

        foreach ($this->rows($type, $user) as $row) {
            $best = 0;

            foreach ($this->candidateNames($row) as $name) {
                $candidate = $this->normalise($name);

                foreach ($needleForms as $form) {
                    similar_text($form, $candidate, $percent);
                    $best = max($best, (int) round($percent));
                }
            }

            if ($best >= self::SUGGESTION_THRESHOLD) {
                $suggestions[] = [
                    'id' => (int) $row->getKey(),
                    'name' => (string) $row->getAttribute('name'),
                    'score' => $best,
                ];
            }
        }

        usort($suggestions, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, self::MAX_SUGGESTIONS);
    }

    /**
     * Trashed rows still occupy the unique name index, so reuse them instead of colliding.
     */
    private function restore(Model $row): void
    {
        if ($this->isTrashed($row) && method_exists($row, 'restore')) {
            $row->restore();
        }
    }

    private function isTrashed(Model $row): bool
    {
        return method_exists($row, 'trashed') && $row->trashed();
    }

    /**
     * @return array<int, string>
     */
    private function candidateNames(Model $row): array
    {
        if (! $row instanceof Unit) {
            return [(string) $row->getAttribute('name')];
        }

        return array_values(array_filter([
            $row->name,
            $row->name_shortcut,
            $row->name_plural,
            $row->name_plural_shortcut,
        ]));
    }

    /**
     * @return Collection<int, Model>
     */
    private function rows(LookupType $type, ?User $user): Collection
    {
        return $this->cache[$type->value] ??= $this->load($type, $user);
    }

    /**
     * @return Collection<int, Model>
     */
    private function load(LookupType $type, ?User $user): Collection
    {
        $query = $type->query();

        if ($type === LookupType::Cookbook) {
            $query->withoutGlobalScope('author_name');

            if (! $user?->admin) {
                $query->where('author_id', $user?->author_id);
            }
        }

        /** @var Collection<int, Model> */
        return $query->get();
    }

    /**
     * Also drops a trailing parenthetical, so an AI echoing a listed "Gramm (g)" back
     * still resolves to "Gramm".
     */
    private function normalise(string $value): string
    {
        $value = (string) preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($value));

        return mb_strtolower(trim($value));
    }

    /**
     * Full option list for a lookup type, used to populate the manual selects.
     *
     * @return array<int, string>
     */
    public function options(LookupType $type, ?User $user): array
    {
        $options = [];

        foreach ($this->rows($type, $user) as $row) {
            if ($this->isTrashed($row)) {
                continue;
            }

            $options[(int) $row->getKey()] = (string) $row->getAttribute('name');
        }

        asort($options);

        return $options;
    }

    /**
     * Creates a missing lookup row, guarding against a concurrent or trashed duplicate.
     */
    public function create(LookupType $type, string $value, ?User $user): int
    {
        if (! $type->canBeCreatedBy($user)) {
            abort(403);
        }

        if ($existing = $this->match($type, $value, $user)) {
            return $existing;
        }

        $model = $type->model();

        $attributes = ['name' => $value];

        if ($type === LookupType::Cookbook) {
            $attributes['author_id'] = $user?->author_id;
        }

        /** @var Model $row */
        $row = $model::query()->create($attributes);

        if (isset($this->cache[$type->value])) {
            $this->cache[$type->value]->push($row);
        }

        return (int) $row->getKey();
    }
}
