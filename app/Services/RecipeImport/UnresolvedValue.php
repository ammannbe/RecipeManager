<?php

namespace App\Services\RecipeImport;

class UnresolvedValue
{
    /**
     * @param  array<int, array{id: int, name: string, score: int}>  $suggestions
     * @param  array<int, string>  $context  ingredients this value appears on
     */
    public function __construct(
        public readonly LookupType $type,
        public readonly string $value,
        public readonly array $suggestions,
        public readonly bool $canCreate,
        public readonly array $context = [],
    ) {}

    /**
     * @param  array<int, string>  $context
     */
    public function withContext(array $context): self
    {
        return new self($this->type, $this->value, $this->suggestions, $this->canCreate, $context);
    }

    /**
     * The suggestion confident enough to preselect, if any.
     */
    public function preselectedId(): ?int
    {
        $best = $this->suggestions[0] ?? null;

        if ($best === null || $best['score'] < RelationResolver::PRESELECT_THRESHOLD) {
            return null;
        }

        return $best['id'];
    }

    /**
     * Stable key used to address this value in the resolution form state.
     */
    public function key(): string
    {
        return $this->type->value.':'.mb_strtolower($this->value);
    }
}
