<?php

namespace App\Services\RecipeImport;

class ResolutionReport
{
    /**
     * @param  array<string, int>  $resolved  lookup key => model id
     * @param  array<int, UnresolvedValue>  $unresolved
     */
    public function __construct(
        public readonly array $resolved,
        public readonly array $unresolved,
    ) {}

    public function isComplete(): bool
    {
        return $this->unresolved === [];
    }

    public static function key(LookupType $type, string $value): string
    {
        return $type->value.':'.mb_strtolower($value);
    }
}
