<?php

namespace App\Models;

use App\Enums\HouseholdRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class HouseholdMembership extends Pivot
{
    protected $table = 'household_user';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'household_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected $casts = [
        'role' => HouseholdRole::class,
        'joined_at' => 'datetime',
    ];
}
