<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Repairs UTF-8 text that was decoded as Windows-1252 once ("Â½" → "½", "Ã„" → "Ä", "â€“" → "–"),
 * a leftover of an old import. A decoded "ß" becomes "ss" (Swiss spelling). Soft-deleted rows are
 * fixed too; timestamps are left alone.
 */
class FixMojibake extends Command
{
    protected $signature = 'text:fix-encoding
        {--dry-run : Show the changes without saving them}';

    protected $description = 'Repair double-encoded UTF-8 (e.g. "Â½ Std.") in recipes and lookup names';

    /**
     * Text columns to check per table.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'recipes' => ['name', 'instructions', 'source', 'serving_type'],
        'ingredient_groups' => ['name'],
        'foods' => ['name'],
        'units' => ['name', 'name_shortcut', 'name_plural', 'name_plural_shortcut'],
        'ingredient_attributes' => ['name'],
        'tags' => ['name'],
        'categories' => ['name'],
        'cookbooks' => ['name'],
        'authors' => ['name'],
    ];

    /**
     * A UTF-8 lead byte (2 or 3 byte sequence) followed by its continuation bytes, each read as
     * Windows-1252: Latin-1 range U+0080–U+00BF plus the Windows-1252 specials of 0x80–0x9F.
     */
    private const PATTERN = '/(?:[\x{C2}-\x{DF}]|[\x{E0}-\x{EF}][\x{80}-\x{BF}\x{152}\x{153}\x{160}\x{161}\x{178}\x{17D}\x{17E}\x{192}\x{2C6}\x{2DC}\x{2013}\x{2014}\x{2018}-\x{201A}\x{201C}-\x{201E}\x{2020}-\x{2022}\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2122}])[\x{80}-\x{BF}\x{152}\x{153}\x{160}\x{161}\x{178}\x{17D}\x{17E}\x{192}\x{2C6}\x{2DC}\x{2013}\x{2014}\x{2018}-\x{201A}\x{201C}-\x{201E}\x{2020}-\x{2022}\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2122}]/u';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $records = 0;

        DB::transaction(function () use ($dryRun, &$rows, &$records): void {
            foreach (self::COLUMNS as $table => $columns) {
                DB::table($table)->orderBy('id')->select(['id', ...$columns])->each(
                    function (object $record) use ($table, $columns, $dryRun, &$rows, &$records): void {
                        $changes = [];

                        foreach ($columns as $column) {
                            $value = $record->{$column};

                            if (! is_string($value) || $value === '') {
                                continue;
                            }

                            $fixed = self::fix($value);

                            if ($fixed !== $value) {
                                $changes[$column] = $fixed;
                                $rows[] = [$table, $record->id, $column, ...self::excerpt($value, $fixed)];
                            }
                        }

                        if ($changes === []) {
                            return;
                        }

                        $records++;

                        if (! $dryRun) {
                            DB::table($table)->where('id', $record->id)->update($changes);
                        }
                    }
                );
            }
        });

        if ($rows === []) {
            $this->info('No broken text found.');

            return self::SUCCESS;
        }

        $this->table(['Table', 'ID', 'Column', 'Before', 'After'], $rows);
        $this->info(sprintf('%d %s.', $records, $dryRun ? 'records would be fixed' : 'records fixed'));

        return self::SUCCESS;
    }

    public static function fix(string $value): string
    {
        return (string) preg_replace_callback(self::PATTERN, function (array $match): string {
            $bytes = mb_convert_encoding($match[0], 'Windows-1252', 'UTF-8');

            if (! mb_check_encoding($bytes, 'UTF-8') || mb_strlen($bytes, 'UTF-8') !== 1 || ! self::isLatinOrPunctuation($bytes)) {
                return $match[0];
            }

            return $bytes === 'ß' ? 'ss' : $bytes;
        }, $value);
    }

    /**
     * Keeps real text like "×½" (would decode to a Hebrew letter) untouched: recipes only use Latin
     * letters, punctuation, currency and letter-like symbols.
     */
    private static function isLatinOrPunctuation(string $char): bool
    {
        $code = mb_ord($char, 'UTF-8');

        return ($code >= 0x80 && $code < 0x250)
            || ($code >= 0x2000 && $code < 0x20D0)
            || ($code >= 0x2100 && $code < 0x2190);
    }

    /**
     * Short before/after context around the first change.
     *
     * @return array{0: string, 1: string}
     */
    private static function excerpt(string $before, string $after): array
    {
        $start = mb_strlen(Str::before($before, (string) Str::of($before)->match(self::PATTERN)));
        $from = max(0, $start - 20);

        return [
            Str::of(mb_substr($before, $from, 50))->squish()->toString(),
            Str::of(mb_substr($after, $from, 50))->squish()->toString(),
        ];
    }
}
