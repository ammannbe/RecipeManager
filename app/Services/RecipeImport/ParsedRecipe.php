<?php

namespace App\Services\RecipeImport;

class ParsedRecipe
{
    /**
     * @param  array<int, string>  $tags
     * @param  array<int, ParsedIngredient>  $ingredients
     * @param  array<int, ParsedGroup>  $groups
     * @param  array<int, array{filename: string, data: string}>  $photos
     */
    public function __construct(
        public readonly string $name,
        public readonly string $category,
        public readonly ?string $author,
        public readonly ?string $cookbook,
        public readonly ?int $servings,
        public readonly ?string $servingType,
        public readonly string $complexity,
        public readonly ?string $preparationTime,
        public readonly string $instructions,
        public readonly array $tags,
        public readonly array $ingredients,
        public readonly array $groups,
        public readonly array $photos,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $ingredients = [];

        foreach (self::wrap($data['ingredients'] ?? []) as $ingredient) {
            if (is_array($ingredient)) {
                $ingredients[] = ParsedIngredient::fromArray($ingredient);
            }
        }

        $groups = [];

        foreach (self::wrap($data['ingredient_groups'] ?? []) as $group) {
            if (is_array($group)) {
                $groups[] = ParsedGroup::fromArray($group);
            }
        }

        $tags = [];

        foreach (self::wrap($data['tags'] ?? []) as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $tags[] = trim($tag);
            }
        }

        $photos = [];

        foreach (self::wrap($data['photos'] ?? []) as $photo) {
            if (is_array($photo) && is_string($photo['data'] ?? null)) {
                $photos[] = [
                    'filename' => trim((string) ($photo['filename'] ?? '')),
                    'data' => $photo['data'],
                ];
            }
        }

        return new self(
            name: trim((string) ($data['name'] ?? '')),
            category: trim((string) ($data['category'] ?? '')),
            author: self::string($data['author'] ?? null),
            cookbook: self::string($data['cookbook'] ?? null),
            servings: is_numeric($data['servings'] ?? null) ? (int) round((float) $data['servings']) : null,
            servingType: self::string($data['serving_type'] ?? null),
            complexity: trim((string) ($data['complexity'] ?? '')),
            preparationTime: self::string($data['preparation_time'] ?? null),
            instructions: trim((string) ($data['instructions'] ?? '')),
            tags: array_values(array_unique($tags)),
            ingredients: $ingredients,
            groups: $groups,
            photos: $photos,
        );
    }

    /**
     * Every ingredient of the recipe, grouped or not, including alternatives.
     *
     * @return array<int, ParsedIngredient>
     */
    public function allIngredients(): array
    {
        $all = [];

        foreach ($this->ingredients as $ingredient) {
            $all[] = $ingredient;
            $all = array_merge($all, $ingredient->alternatives);
        }

        foreach ($this->groups as $group) {
            foreach ($group->ingredients as $ingredient) {
                $all[] = $ingredient;
                $all = array_merge($all, $ingredient->alternatives);
            }
        }

        return $all;
    }

    private static function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return trim($value) === '' ? null : trim($value);
    }

    /**
     * @return array<int, mixed>
     */
    private static function wrap(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
