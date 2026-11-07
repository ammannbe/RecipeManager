<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A cookbook shared with a household: the grants apply to every member of it,
 * exactly like a CookbookMembership does for one user.
 */
class CookbookHouseholdShare extends Pivot
{
    public const GRANTS = CookbookMembership::GRANTS;

    protected $table = 'cookbook_household';

    public $incrementing = true;

    protected $fillable = [
        'cookbook_id',
        'household_id',
        'can_admin',
        'can_read',
        'can_create',
        'can_update',
        'can_delete',
    ];

    protected $casts = [
        'can_admin' => 'boolean',
        'can_read' => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
    ];
}
