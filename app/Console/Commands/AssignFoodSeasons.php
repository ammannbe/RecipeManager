<?php

namespace App\Console\Commands;

use App\Models\Food;
use App\Services\Season;
use Illuminate\Console\Command;

/**
 * Assigns the Swiss seasonal calendar (database/data/food-seasons.php) to foods with exactly
 * matching names, approved foods and requests alike. Months set by hand win unless --force.
 */
class AssignFoodSeasons extends Command
{
    protected $signature = 'foods:seasons
        {--dry-run : Show the changes without saving them}
        {--force : Overwrite foods that already have months}';

    protected $description = 'Assign season and storage months from database/data/food-seasons.php to foods with matching names';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        /** @var array<string, array{0: list<int>, 1: list<int>}> $data */
        $data = require database_path('data/food-seasons.php');

        $seasons = [];

        foreach ($data as $name => [$fresh, $storage]) {
            $seasons[self::normalize($name)] = [
                'name' => $name,
                'fresh' => self::months($fresh),
                'storage' => self::months($storage),
            ];
        }

        $rows = [];
        $matched = [];
        $candidates = [];
        $counts = ['updated' => 0, 'kept' => 0, 'unchanged' => 0];

        foreach (Food::query()->orderBy('name')->get() as $food) {
            $key = self::normalize($food->name);
            $season = $seasons[$key] ?? null;

            if ($season === null) {
                $contained = collect($seasons)
                    ->filter(fn (array $season, string $name): bool => str_contains($key, $name))
                    ->pluck('name');

                if ($contained->isNotEmpty()) {
                    $candidates[] = "{$food->name} ({$contained->implode(', ')})";
                }

                continue;
            }

            $matched[$key] = true;

            if ($food->season_months === $season['fresh'] && $food->storage_months === $season['storage']) {
                $counts['unchanged']++;

                continue;
            }

            if (! $force && ($food->season_months !== null || $food->storage_months !== null)) {
                $counts['kept']++;
                $status = 'kept (already set, use --force)';
            } else {
                $counts['updated']++;
                $status = $dryRun ? 'would update' : 'updated';

                if (! $dryRun) {
                    $food->update(['season_months' => $season['fresh'], 'storage_months' => $season['storage']]);
                }
            }

            $rows[] = [
                $food->id,
                $food->name,
                Season::summary($season['fresh']),
                Season::summary($season['storage']),
                $status,
            ];
        }

        if ($rows === []) {
            $this->info('No changes.');
        } else {
            $this->table(['ID', 'Food', 'Fresh', 'Storage', 'Status'], $rows);
        }

        $this->info(sprintf(
            '%d %s, %d kept, %d unchanged.',
            $counts['updated'],
            $dryRun ? 'would be updated' : 'updated',
            $counts['kept'],
            $counts['unchanged'],
        ));

        $missing = collect($seasons)->reject(fn (array $season, string $key): bool => isset($matched[$key]))->pluck('name');

        $this->newLine();
        $this->line('<comment>Data names without a matching food:</comment>');
        $this->printList($missing->all());

        $this->newLine();
        $this->line('<comment>Foods containing a data name, not matched (assign manually):</comment>');
        $this->printList($candidates);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function printList(array $lines): void
    {
        if ($lines === []) {
            $this->line('  (none)');

            return;
        }

        foreach ($lines as $line) {
            $this->line("  - {$line}");
        }
    }

    private static function normalize(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    /**
     * Same shape as the Food casts return: sorted integers, null for none.
     *
     * @param  list<int>  $months
     * @return list<int>|null
     */
    private static function months(array $months): ?array
    {
        $months = array_values(array_unique($months));
        sort($months);

        return $months === [] ? null : $months;
    }
}
