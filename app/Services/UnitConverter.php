<?php

namespace App\Services;

use App\Enums\Measure;
use App\Models\Ingredient;
use App\Models\Unit;

/**
 * Converts ingredient amounts into the food's measure (weight or volume): units of the same
 * measure by their base amount, mass ↔ volume by the food's density, piece units by the
 * food's weight per piece. The recipe page multiplies the factor in JS (`recipeServings`),
 * the API uses display(); both choose the shown unit the same way.
 */
class UnitConverter
{
    /**
     * Grams or milliliters (in the target measure) per one unit of the ingredient, or null if it
     * cannot be converted. Needs the ingredient's unit, food and food.unitWeights.
     *
     * @return array{measure: Measure, factor: float}|null
     */
    public function factor(Ingredient $ingredient): ?array
    {
        $unit = $ingredient->unit;
        $food = $ingredient->food;

        if ($unit === null || $food === null) {
            return null;
        }

        if ($unit->measure !== null && $unit->base_amount !== null && $unit->base_amount > 0) {
            $from = $unit->measure;
            $amount = $unit->base_amount;
        } else {
            $weight = $food->unitWeights->firstWhere('unit_id', $unit->id);

            if ($weight === null || $weight->grams <= 0) {
                return null;
            }

            $from = Measure::Mass;
            $amount = $weight->grams;
        }

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
     * The factor for the recipe page: only for ingredients with an amount whose display changes.
     *
     * @return array{measure: Measure, factor: float}|null
     */
    public function pageConversion(Ingredient $ingredient): ?array
    {
        if ($ingredient->amount === null && $ingredient->amount_max === null) {
            return null;
        }

        return $this->changes($ingredient) ? $this->factor($ingredient) : null;
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
