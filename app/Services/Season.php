<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Month helpers for the Swiss seasonal calendar (foods.season_months / storage_months).
 */
class Season
{
    public const TIMEZONE = 'Europe/Zurich';

    public static function currentMonth(): int
    {
        return Carbon::now(self::TIMEZONE)->month;
    }

    /**
     * The given month if valid (1–12), otherwise the current one.
     */
    public static function month(mixed $month): int
    {
        $month = filter_var($month, FILTER_VALIDATE_INT);

        return is_int($month) && $month >= 1 && $month <= 12 ? $month : self::currentMonth();
    }

    public static function monthName(int $month): string
    {
        $date = Carbon::now()->setDate(2000, $month, 1);
        $date->locale(app()->getLocale());

        return $date->isoFormat('MMMM');
    }

    /**
     * First three letters of the localized name ("Mär", "Okt"), shorter than Carbon's abbreviations.
     */
    public static function shortMonthName(int $month): string
    {
        return mb_substr(self::monthName($month), 0, 3);
    }

    /**
     * @return array<int, string>
     */
    public static function monthNames(): array
    {
        $names = [];

        foreach (range(1, 12) as $month) {
            $names[$month] = self::monthName($month);
        }

        return $names;
    }

    /**
     * Compact ranges like "Jun–Sep" or "Okt–Mär, Jun", wrapping around the turn of the year.
     *
     * @param  array<int, int>|null  $months
     */
    public static function summary(?array $months): string
    {
        $months = array_values(array_unique(array_map('intval', $months ?? [])));

        if ($months === []) {
            return '';
        }

        if (count($months) === 12) {
            return __('All year');
        }

        $ranges = [];

        foreach (range(1, 12) as $start) {
            if (! in_array($start, $months, true) || in_array(self::previous($start), $months, true)) {
                continue;
            }

            $end = $start;

            while (in_array(self::next($end), $months, true)) {
                $end = self::next($end);
            }

            $ranges[] = $start === $end
                ? self::shortMonthName($start)
                : self::shortMonthName($start).'–'.self::shortMonthName($end);
        }

        return implode(', ', $ranges);
    }

    public static function previous(int $month): int
    {
        return $month === 1 ? 12 : $month - 1;
    }

    public static function next(int $month): int
    {
        return $month === 12 ? 1 : $month + 1;
    }
}
