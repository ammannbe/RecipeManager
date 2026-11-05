<?php

namespace App\Services;

use App\Enums\Measure;
use App\Models\Food;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Conversion and season data of a food created while importing (wizard, MCP, AI estimate): measure,
 * density, grams per piece unit, fresh season and storage months, the same fields admins edit in
 * the Food resource.
 *
 * @phpstan-type Conversion array{measure: Measure|null, density: float|null, weights: array<int, float>, season_months: list<int>, storage_months: list<int>}
 */
class FoodConversions
{
    /** Grams per milliliter: from puffed cereals and dried herbs up to salt and honey, with some margin. */
    public const MIN_DENSITY = 0.05;

    public const MAX_DENSITY = 3;

    /** Grams per piece: a whole pumpkin or ham still fits. */
    public const MAX_GRAMS = 10000;

    /**
     * Piece units (units without measure) the user may use, id => name.
     *
     * @return array<int, string>
     */
    public function pieceUnits(?User $user): array
    {
        return Unit::query()->visibleTo($user)->whereNull('measure')->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * The piece unit by name, shortcut or plural (case-insensitive).
     */
    public function pieceUnitId(string $name, ?User $user): ?int
    {
        $name = mb_strtolower(trim($name));

        foreach (Unit::query()->visibleTo($user)->whereNull('measure')->get() as $unit) {
            $names = array_map(fn (string $value): string => mb_strtolower($value), array_filter([$unit->name, $unit->name_shortcut, $unit->name_plural, $unit->name_plural_shortcut]));

            if (in_array($name, $names, true)) {
                return $unit->id;
            }
        }

        return null;
    }

    /**
     * Checks the input of the import (form state or MCP): measure, density, weights as a list of
     * {unit_id, grams} and season_months / storage_months as lists of distinct months 1–12.
     *
     * @param  array<string, mixed>  $input
     * @return Conversion
     *
     * @throws ValidationException
     */
    public function validate(array $input, ?User $user, string $food): array
    {
        $data = [
            'measure' => blank($input['measure'] ?? null) ? null : $input['measure'],
            'density' => blank($input['density'] ?? null) ? null : $input['density'],
            'weights' => array_values(array_filter((array) ($input['weights'] ?? []), 'is_array')),
            'season_months' => $input['season_months'] ?? [],
            'storage_months' => $input['storage_months'] ?? [],
        ];

        $validator = Validator::make($data, [
            'measure' => ['nullable', Rule::enum(Measure::class)],
            'density' => ['nullable', 'numeric', 'min:'.self::MIN_DENSITY, 'max:'.self::MAX_DENSITY],
            'weights' => ['array'],
            'weights.*.unit_id' => ['required', 'integer', Rule::in(array_keys($this->pieceUnits($user))), 'distinct'],
            'weights.*.grams' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_GRAMS],
            'season_months' => ['array', 'max:12'],
            'season_months.*' => ['required', 'integer', 'between:1,12', 'distinct'],
            'storage_months' => ['array', 'max:12'],
            'storage_months.*' => ['required', 'integer', 'between:1,12', 'distinct'],
        ], [], [
            'measure' => __('Convert to').' ('.$food.')',
            'density' => __('Density (g per ml)').' ('.$food.')',
            'weights.*.unit_id' => __('Unit').' ('.$food.')',
            'weights.*.grams' => __('Grams').' ('.$food.')',
            'season_months' => __('Fresh season').' ('.$food.')',
            'season_months.*' => __('Fresh season').' ('.$food.')',
            'storage_months' => __('From storage').' ('.$food.')',
            'storage_months.*' => __('From storage').' ('.$food.')',
        ]);

        $validator->validate();

        $weights = [];

        foreach ($data['weights'] as $weight) {
            $weights[(int) $weight['unit_id']] = round((float) $weight['grams'], 2);
        }

        return [
            'measure' => $data['measure'] !== null ? Measure::from((string) $data['measure']) : null,
            'density' => $data['density'] !== null ? round((float) $data['density'], 3) : null,
            'weights' => $weights,
            'season_months' => self::months($data['season_months']),
            'storage_months' => self::months($data['storage_months']),
        ];
    }

    /**
     * Valid months (1–12) as sorted distinct integers; anything else is dropped.
     *
     * @return list<int>
     */
    public static function months(mixed $months): array
    {
        $result = [];

        foreach (is_array($months) ? $months : [] as $month) {
            $month = filter_var($month, FILTER_VALIDATE_INT);

            if (is_int($month) && $month >= 1 && $month <= 12) {
                $result[$month] = $month;
            }
        }

        ksort($result);

        return array_values($result);
    }

    /**
     * An AI estimate reduced to valid values: unknown measures, densities out of range, unknown
     * units, impossible weights and invalid months are dropped.
     *
     * @param  array<string, mixed>  $estimate  measure, density, piece_weights: list<{unit, grams}>, season_months, storage_months
     * @param  array<int, string>  $pieceUnits  id => name
     * @return array{measure: string|null, density: float|null, weights: array<int, float>, season_months: list<int>, storage_months: list<int>}
     */
    public function clean(array $estimate, array $pieceUnits): array
    {
        $density = is_numeric($estimate['density'] ?? null) ? (float) $estimate['density'] : null;
        $units = array_flip(array_map('mb_strtolower', $pieceUnits));
        $season = self::months($estimate['season_months'] ?? []);
        $weights = [];

        foreach ((array) ($estimate['piece_weights'] ?? []) as $weight) {
            $unitId = is_array($weight) ? ($units[mb_strtolower((string) ($weight['unit'] ?? ''))] ?? null) : null;
            $grams = is_array($weight) && is_numeric($weight['grams'] ?? null) ? (float) $weight['grams'] : 0.0;

            if ($unitId !== null && $grams > 0 && $grams <= self::MAX_GRAMS) {
                $weights[$unitId] = round(max($grams, 0.01), 2);
            }
        }

        return [
            'measure' => Measure::tryFrom((string) ($estimate['measure'] ?? ''))?->value,
            'density' => $density !== null && $density >= self::MIN_DENSITY && $density <= self::MAX_DENSITY ? round($density, 3) : null,
            'weights' => $weights,
            'season_months' => $season,
            // Storage months are the ones without fresh harvest.
            'storage_months' => array_values(array_diff(self::months($estimate['storage_months'] ?? []), $season)),
        ];
    }

    /**
     * Stores checked values on a food (only set values; existing piece weights of other units stay).
     *
     * @param  Conversion  $conversion
     */
    public function apply(Food $food, array $conversion): void
    {
        if ($conversion['measure'] !== null) {
            $food->measure = $conversion['measure'];
        }

        if ($conversion['density'] !== null) {
            $food->density = $conversion['density'];
        }

        if ($conversion['season_months'] !== []) {
            $food->season_months = $conversion['season_months'];
        }

        if ($conversion['storage_months'] !== []) {
            $food->storage_months = $conversion['storage_months'];
        }

        $food->save();

        foreach ($conversion['weights'] as $unitId => $grams) {
            $food->unitWeights()->updateOrCreate(['unit_id' => $unitId], ['grams' => $grams]);
        }
    }
}
