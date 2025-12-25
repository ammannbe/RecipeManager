<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

trait FilterScope
{
    /**
     * Model filtern
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  Eloquent Builder
     * @param  array  $filter  e.g. ['position' => 5, 'ingredient_group_id' => 3]
     * @param  string  $method  or|and
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter(Builder $query, ?array $filter, ?string $method = 'and'): Builder
    {
        if (!$filter) {
            return $query;
        }

        $method = $this->validateFilterMethod($method);

        return $query->where(function ($q) use ($filter, $method) {
            foreach ($filter as $key => $value) {
                if (is_numeric($value) || $this->filterValueIsTime($value)) {
                    $q->{$method}($key, $value);
                    continue;
                }

                if ($value === null) {
                    $q->{$method}($key, null);
                    continue;
                }

                if ($value === 'NOT NULL') {
                    $q->{"{$method}NotNull"}($key);
                    continue;
                }

                $q->{$method}($key, 'LIKE', "%{$value}%");
            }
        });
    }

    /**
     * Get the correct SQL filter method
     *
     * @param  string  $method
     * @return string e.g. where|orWhere
     */
    private function validateFilterMethod(?string $method): string
    {
        if ($method === 'or') {
            return 'orWhere';
        }

        return 'where';
    }

    /**
     * Check if the filter value is a (valid) time
     *
     * @param  string  $value
     * @param  string  $format
     * @return bool
     */
    private function filterValueIsTime(string $value, string $format = 'H:i'): bool
    {
        $date = \DateTime::createFromFormat('H:i', $value);
        if ($date && $date->format('H:i') === $value) {
            return true;
        }

        return false;
    }
}
