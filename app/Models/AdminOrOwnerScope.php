<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

class AdminOrOwnerScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (!auth()->check()) {
            $builder->whereUserId(null);
            return;
        }
        $user = auth()->user();
        if (!$user->admin) {
            $builder->whereUserId($user->id);
        }
    }
}
