<?php

namespace App\Console\Commands;

use App\Enums\Measure;
use App\Models\Food;
use App\Models\FoodUnitWeight;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Assigns the unit converter data (database/data/food-conversions.php) to units and foods with
 * exactly matching names. Values set by hand win unless --force.
 */
class AssignFoodConversions extends Command
{
    protected $signature = 'foods:conversions
        {--dry-run : Show the changes without saving them}
        {--force : Overwrite values that are already set}';

    protected $description = 'Assign unit measures, food densities and piece weights from database/data/food-conversions.php';

    private bool $dryRun = false;

    private bool $force = false;

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->force = (bool) $this->option('force');

        /** @var array{units: array<string, array{0: string, 1: float|int}>, foods: array<string, array{0: string, 1: float|null, 2?: array<string, float|int>}>} $data */
        $data = require database_path('data/food-conversions.php');

        $units = Unit::query()->get()->keyBy(fn (Unit $unit): string => self::normalize($unit->name));
        $foods = Food::query()->with('unitWeights')->get()->keyBy(fn (Food $food): string => self::normalize($food->name));

        $missing = [];

        foreach ($data['units'] as $name => [$measure, $amount]) {
            $unit = $units->get(self::normalize($name));

            if ($unit === null) {
                $missing[] = "unit {$name}";

                continue;
            }

            $this->assign($unit, ['measure' => Measure::from($measure), 'base_amount' => (float) $amount], "unit {$unit->name}");
        }

        foreach ($data['foods'] as $name => $entry) {
            $food = $foods->get(self::normalize($name));

            if ($food === null) {
                $missing[] = "food {$name}";

                continue;
            }

            $this->assign($food, [
                'measure' => Measure::from($entry[0]),
                'density' => $entry[1] !== null ? (float) $entry[1] : null,
            ], $food->name);

            foreach ($entry[2] ?? [] as $unitName => $grams) {
                $unit = $units->get(self::normalize($unitName));

                if ($unit === null) {
                    $missing[] = "unit {$unitName} (weight of {$name})";

                    continue;
                }

                $this->assignWeight($food, $unit, (float) $grams);
            }
        }

        if ($this->rows === []) {
            $this->info('No changes.');
        } else {
            $this->table(['Entry', 'Value', 'Status'], $this->rows);
        }

        $this->newLine();
        $this->line('<comment>Data names without a matching unit or food:</comment>');

        foreach ($missing === [] ? ['(none)'] : $missing as $line) {
            $this->line("  - {$line}");
        }

        return self::SUCCESS;
    }

    /**
     * Sets the values unless they are equal; other values set by hand are kept without --force.
     *
     * @param  array<string, mixed>  $values
     */
    private function assign(Unit|Food $model, array $values, string $label): void
    {
        $current = collect($values)->map(fn (mixed $value, string $key): mixed => $model->getAttribute($key));

        if ($current->all() == $values) {
            return;
        }

        $value = collect($values)->map(fn (mixed $value): string => $value instanceof Measure ? $value->value : (string) ($value ?? '–'))->implode(', ');

        if (! $this->force && $current->filter(fn (mixed $value): bool => $value !== null)->isNotEmpty()) {
            $this->rows[] = [$label, $value, 'kept (already set, use --force)'];

            return;
        }

        if (! $this->dryRun) {
            $model->update($values);
        }

        $this->rows[] = [$label, $value, $this->dryRun ? 'would update' : 'updated'];
    }

    private function assignWeight(Food $food, Unit $unit, float $grams): void
    {
        /** @var Collection<int, FoodUnitWeight> $weights */
        $weights = $food->unitWeights;
        $weight = $weights->firstWhere('unit_id', $unit->id);
        $label = "{$food->name} / {$unit->name}";

        if ($weight !== null && $weight->grams === $grams) {
            return;
        }

        if ($weight !== null && ! $this->force) {
            $this->rows[] = [$label, "{$grams} g", 'kept (already set, use --force)'];

            return;
        }

        if (! $this->dryRun) {
            FoodUnitWeight::query()->updateOrCreate(['food_id' => $food->id, 'unit_id' => $unit->id], ['grams' => $grams]);
        }

        $this->rows[] = [$label, "{$grams} g", $this->dryRun ? 'would update' : 'updated'];
    }

    private static function normalize(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }
}
