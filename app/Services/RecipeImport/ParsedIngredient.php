<?php

namespace App\Services\RecipeImport;

class ParsedIngredient
{
    /**
     * @param  array<int, string>  $attributes
     * @param  array<int, self>  $alternatives
     */
    public function __construct(
        public readonly ?float $amount,
        public readonly ?float $amountMax,
        public readonly ?string $unit,
        public readonly string $food,
        public readonly array $attributes,
        public readonly array $alternatives,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, bool $allowAlternatives = true): self
    {
        $alternatives = [];

        if ($allowAlternatives) {
            foreach (self::wrap($data['alternatives'] ?? []) as $alternative) {
                if (is_array($alternative)) {
                    $alternatives[] = self::fromArray($alternative, allowAlternatives: false);
                }
            }
        }

        $attributes = [];

        foreach (self::wrap($data['attributes'] ?? []) as $attribute) {
            $attribute = is_string($attribute) ? trim($attribute) : '';

            if ($attribute !== '') {
                $attributes[] = $attribute;
            }
        }

        return new self(
            amount: self::float($data['amount'] ?? null),
            amountMax: self::float($data['amount_max'] ?? null),
            unit: self::string($data['unit'] ?? null),
            food: (string) self::string($data['food'] ?? null),
            attributes: array_values(array_unique($attributes)),
            alternatives: $alternatives,
        );
    }

    private static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
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
