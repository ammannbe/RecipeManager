<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * @param  Builder<self>  $query
     * @param  array<mixed>|string  $fields
     */
    public function scopeSearch(Builder $query, array|string $fields, mixed $value): void
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        if (empty($fields)) {
            return;
        }

        if (empty($value)) {
            return;
        }

        $query->where(function (Builder $q) use ($fields, $value) {
            foreach ($fields as $key => $field) {
                if (is_array($field)) {
                    foreach ($field as $f) {
                        $q->orWhereHas($key, fn (Builder $q2) => $q2->where($f, 'LIKE', "%{$value}%"));
                    }
                } else {
                    $q->orWhere($q->getModel()->getTable().'.'.$field, 'LIKE', "%{$value}%");
                }
            }
        });
    }
}
