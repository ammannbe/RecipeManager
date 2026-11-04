<?php

namespace App\Enums;

/**
 * What a unit measures: units of the same measure convert by their base amount (grams or
 * milliliters), mass and volume via a food's density.
 */
enum Measure: string
{
    case Mass = 'mass';
    case Volume = 'volume';

    public function label(): string
    {
        return match ($this) {
            Measure::Mass => __('Weight'),
            Measure::Volume => __('Volume'),
        };
    }

    public function baseUnit(): string
    {
        return match ($this) {
            Measure::Mass => 'g',
            Measure::Volume => 'ml',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (Measure $measure): array => [$measure->value => $measure->label()])->all();
    }
}
