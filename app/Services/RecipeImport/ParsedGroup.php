<?php

namespace App\Services\RecipeImport;

class ParsedGroup
{
    /**
     * @param  array<int, ParsedIngredient>  $ingredients
     */
    public function __construct(
        public readonly string $name,
        public readonly array $ingredients,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $ingredients = [];

        foreach (is_array($data['ingredients'] ?? null) ? $data['ingredients'] : [] as $ingredient) {
            if (is_array($ingredient)) {
                $ingredients[] = ParsedIngredient::fromArray($ingredient);
            }
        }

        return new self(
            name: trim((string) ($data['name'] ?? '')),
            ingredients: $ingredients,
        );
    }
}
