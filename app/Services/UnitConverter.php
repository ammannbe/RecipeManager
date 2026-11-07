<?php

namespace App\Services;

use App\Enums\Measure;
use App\Models\Ingredient;
use App\Models\Unit;

/**
 * Converts ingredient amounts into the food's measure (weight or volume): units of the same
 * measure by their base amount, mass ↔ volume by the food's density, piece units by the
 * food's weight per piece. The recipe page multiplies the factors in JS (`recipeServings`,
 * display modes metric, volume and cups), the API uses display(); both choose the shown unit the same way.
 */
class UnitConverter
{
    /** Cup modes of the recipe page => name of their cup unit. */
    public const CUP_UNITS = ['cups' => 'Tasse', 'cups_us' => 'Cup'];

    /** Spoon units of both cup modes by name. */
    public const SPOON_UNITS = ['spoon' => 'Esslöffel', 'teaspoon' => 'Teelöffel'];

    /** Fractions of cups (quarters and thirds) and spoons (quarters and ⅛, like measuring spoons). */
    public const CUP_FRACTIONS = [0.0, 1 / 4, 1 / 3, 1 / 2, 2 / 3, 3 / 4];

    public const SPOON_FRACTIONS = [0.0, 1 / 8, 1 / 4, 1 / 2, 3 / 4];

    /**
     * Grams or milliliters (in the target measure) per one unit of the ingredient, or null if it
     * cannot be converted. Needs the ingredient's unit, food and food.unitWeights.
     *
     * @return array{measure: Measure, factor: float}|null
     */
    public function factor(Ingredient $ingredient): ?array
    {
        $food = $ingredient->food;
        $source = $this->source($ingredient);

        if ($food === null || $source === null) {
            return null;
        }

        [$from, $amount] = $source;
        $to = $food->measure ?? $from;

        if ($to === $from) {
            return ['measure' => $to, 'factor' => $amount];
        }

        if ($food->density === null || $food->density <= 0) {
            return null;
        }

        return [
            'measure' => $to,
            'factor' => $to === Measure::Mass ? $amount * $food->density : $amount / $food->density,
        ];
    }

    /**
     * The unit's own measure and g/ml per unit (piece units through the food's weight per piece), or
     * null for pieces without weight and vague amounts.
     *
     * @return array{0: Measure, 1: float}|null
     */
    private function source(Ingredient $ingredient): ?array
    {
        $unit = $ingredient->unit;

        if ($unit === null) {
            return null;
        }

        if ($unit->measure !== null && $unit->base_amount !== null && $unit->base_amount > 0) {
            return [$unit->measure, $unit->base_amount];
        }

        $weight = $ingredient->food?->unitWeights->firstWhere('unit_id', $unit->id);

        return $weight !== null && $weight->grams > 0 ? [Measure::Mass, (float) $weight->grams] : null;
    }

    /**
     * Milliliters per one unit of the ingredient, whatever the food's measure: mass (also piece
     * weights) through the food's density. Null if it cannot be converted.
     */
    public function volumeFactor(Ingredient $ingredient): ?float
    {
        $source = $this->source($ingredient);

        if ($source === null) {
            return null;
        }

        [$from, $amount] = $source;

        if ($from === Measure::Volume) {
            return $amount;
        }

        $density = $ingredient->food?->density;

        return $density !== null && $density > 0 ? $amount / $density : null;
    }

    /**
     * True if converting shows something else than the recipe: another measure, or a unit
     * outside g/kg and ml/dl/l (EL, Tasse, Stück, …).
     */
    public function changes(Ingredient $ingredient): bool
    {
        $factor = $this->factor($ingredient);

        if ($factor === null) {
            return false;
        }

        /** @var Unit $unit */
        $unit = $ingredient->unit;

        if ($unit->measure !== $factor['measure']) {
            return true;
        }

        return ! in_array($unit->base_amount, self::metric($factor['measure']), true);
    }

    /**
     * Base amounts of g/kg or ml/dl/l.
     *
     * @return list<float>
     */
    private static function metric(Measure $measure): array
    {
        return $measure === Measure::Mass ? [1.0, 1000.0] : [1.0, 100.0, 1000.0];
    }

    /**
     * Amounts of a metric unit (kg, l, dl) that need more than two decimals move to the next smaller
     * metric unit where they do not (1/8 l = 0.125 l becomes 1.25 dl, 0.0625 l becomes 62.5 ml), so
     * nothing is rounded away. Other units and amounts are kept.
     *
     * @return array{unit: Unit, amount: float|null, amount_max: float|null}
     */
    public function precise(Unit $unit, ?float $amount, ?float $amountMax): array
    {
        $result = ['unit' => $unit, 'amount' => $amount, 'amount_max' => $amountMax];

        if ($unit->measure === null || $unit->base_amount === null
            || ! in_array($unit->base_amount, self::metric($unit->measure), true)
            || (self::hasTwoDecimals($amount) && self::hasTwoDecimals($amountMax))) {
            return $result;
        }

        $smaller = Unit::query()
            ->where('measure', $unit->measure)
            ->whereNull('requested_by')
            ->whereIn('base_amount', self::metric($unit->measure))
            ->where('base_amount', '<', $unit->base_amount)
            ->orderByDesc('base_amount')
            ->orderBy('id')
            ->get()
            ->unique(fn (Unit $candidate): float => (float) $candidate->base_amount);

        foreach ($smaller as $candidate) {
            $factor = $unit->base_amount / (float) $candidate->base_amount;
            $result = [
                'unit' => $candidate,
                'amount' => $amount !== null ? round($amount * $factor, 6) : null,
                'amount_max' => $amountMax !== null ? round($amountMax * $factor, 6) : null,
            ];

            if (self::hasTwoDecimals($result['amount']) && self::hasTwoDecimals($result['amount_max'])) {
                break;
            }
        }

        return $result;
    }

    private static function hasTwoDecimals(?float $value): bool
    {
        return $value === null || abs(round($value, 2) - $value) < 0.000001;
    }

    /**
     * What the recipe page needs to show an amount in the display modes (`recipeServings` in app.js):
     * `metric` = factor() (null: cannot be converted), `metric_changes` = changes(), `volume` = ml per
     * unit (null: cannot be converted), `volume_changes` = not in ml/dl/l yet. Null for ingredients
     * without amount, piece units without weight and vague amounts: they always stay as entered.
     *
     * @return array{metric: array{measure: Measure, factor: float}|null, metric_changes: bool, volume: float|null, volume_changes: bool}|null
     */
    public function pageConversion(Ingredient $ingredient): ?array
    {
        if (($ingredient->amount === null && $ingredient->amount_max === null) || $this->source($ingredient) === null) {
            return null;
        }

        /** @var Unit $unit */
        $unit = $ingredient->unit;
        $volume = $this->volumeFactor($ingredient);

        return [
            'metric' => $this->factor($ingredient),
            'metric_changes' => $this->changes($ingredient),
            'volume' => $volume,
            'volume_changes' => $volume !== null && ! ($unit->measure === Measure::Volume
                && in_array($unit->base_amount, self::metric(Measure::Volume), true)),
        ];
    }

    /**
     * Display modes of the recipe page that change at least one ingredient, after "original".
     * Cup modes change every ingredient with a volume that is not in one of their units already.
     *
     * @param  iterable<Ingredient>  $ingredients  also the alternatives
     * @param  array<string, array<string, array<string, mixed>>>  $systems  cupSystems()
     * @return list<string>
     */
    public function pageModes(iterable $ingredients, array $systems): array
    {
        $conversions = [];

        foreach ($ingredients as $ingredient) {
            $conversion = $this->pageConversion($ingredient);

            if ($conversion !== null) {
                $conversions[] = [$ingredient->unit_id, $conversion];
            }
        }

        $modes = ['original'];

        foreach (['metric', 'volume'] as $mode) {
            foreach ($conversions as [, $conversion]) {
                if ($conversion[$mode.'_changes']) {
                    $modes[] = $mode;
                    break;
                }
            }
        }

        foreach ($systems as $mode => $system) {
            $unitIds = array_column($system, 'id');

            foreach ($conversions as [$unitId, $conversion]) {
                if ($conversion['volume'] !== null && ! in_array($unitId, $unitIds, true)) {
                    $modes[] = $mode;
                    break;
                }
            }
        }

        return $modes;
    }

    /**
     * Cup and spoon units of the cup modes, by name from the units table (sizes and names as stored):
     * `cups` = Tasse, `cups_us` = Cup, both with Esslöffel and Teelöffel. A mode is left out while one
     * of its units is missing, not approved or has no volume.
     *
     * @return array<string, array{cup: array<string, mixed>, spoon: array<string, mixed>, teaspoon: array<string, mixed>}>
     */
    public function cupSystems(): array
    {
        $names = [...self::CUP_UNITS, ...self::SPOON_UNITS];

        $units = Unit::query()
            ->whereIn('name', $names)
            ->whereNull('requested_by')
            ->where('measure', Measure::Volume)
            ->where('base_amount', '>', 0)
            ->get()
            ->keyBy(fn (Unit $unit): string => mb_strtolower($unit->name));

        $data = function (string $name) use ($units): ?array {
            $unit = $units->get(mb_strtolower($name));

            return $unit === null ? null : [
                'id' => $unit->id,
                'ml' => (float) $unit->base_amount,
                'name' => $unit->name,
                'nameShortcut' => $unit->name_shortcut,
                'namePlural' => $unit->name_plural,
                'namePluralShortcut' => $unit->name_plural_shortcut,
            ];
        };

        $spoon = $data(self::SPOON_UNITS['spoon']);
        $teaspoon = $data(self::SPOON_UNITS['teaspoon']);
        $systems = [];

        foreach (self::CUP_UNITS as $mode => $name) {
            $cup = $data($name);

            if ($cup !== null && $spoon !== null && $teaspoon !== null) {
                $systems[$mode] = ['cup' => $cup, 'spoon' => $spoon, 'teaspoon' => $teaspoon];
            }
        }

        return $systems;
    }

    /**
     * An amount in ml shown in cups and spoons (by the larger amount): the cup from ¼ cup, otherwise
     * the tablespoon from 1 tablespoon, otherwise the teaspoon, rounded to the nearest friendly
     * fraction (CUP_FRACTIONS, SPOON_FRACTIONS). Null when it rounds to nothing (a drop stays a drop).
     * Mirrored by cupAmount() in resources/js/app.js.
     *
     * @param  array{cup: float, spoon: float, teaspoon: float}  $sizes  ml per unit
     * @return array{amount: float|null, amount_max: float|null, unit: 'cup'|'spoon'|'teaspoon'}|null
     */
    public static function cups(array $sizes, ?float $amount, ?float $amountMax): ?array
    {
        $reference = $amountMax ?? $amount ?? 0.0;

        $unit = match (true) {
            $reference >= $sizes['cup'] / 4 => 'cup',
            $reference >= $sizes['spoon'] => 'spoon',
            default => 'teaspoon',
        };

        $fractions = $unit === 'cup' ? self::CUP_FRACTIONS : self::SPOON_FRACTIONS;
        $round = fn (?float $value): ?float => $value === null ? null : self::fraction($value / $sizes[$unit], $fractions);

        if ($round($reference) == 0) {
            return null;
        }

        return ['amount' => $round($amount), 'amount_max' => $round($amountMax), 'unit' => $unit];
    }

    /**
     * The nearest whole number plus one of the fractions (ties go to the smaller one).
     *
     * @param  list<float>  $fractions  starting with 0
     */
    private static function fraction(float $value, array $fractions): float
    {
        $whole = floor($value);
        $best = $whole;

        foreach ([...array_map(fn (float $fraction): float => $whole + $fraction, $fractions), $whole + 1] as $candidate) {
            if (abs($candidate - $value) < abs($best - $value)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Amounts in g/ml shown as g or kg, ml, dl or l (by the larger amount), rounded for reading.
     * Mirrored by convertedAmount() in resources/js/app.js.
     *
     * @return array{amount: float|null, amount_max: float|null, unit: string}
     */
    public static function display(Measure $measure, ?float $amount, ?float $amountMax): array
    {
        $reference = $amountMax ?? $amount ?? 0.0;

        [$unit, $divisor] = match ($measure) {
            Measure::Mass => $reference >= 1000 ? ['kg', 1000] : ['g', 1],
            Measure::Volume => match (true) {
                $reference >= 1000 => ['l', 1000],
                $reference >= 100 => ['dl', 100],
                default => ['ml', 1],
            },
        };

        $round = function (?float $value) use ($divisor): ?float {
            if ($value === null) {
                return null;
            }

            $value /= $divisor;

            // g and ml whole above 10, otherwise one decimal; kg, dl and l two decimals.
            return round($value, $divisor === 1 ? ($value < 10 ? 1 : 0) : 2);
        };

        return ['amount' => $round($amount), 'amount_max' => $round($amountMax), 'unit' => $unit];
    }
}
