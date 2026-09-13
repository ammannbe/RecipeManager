<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CookbookMembership extends Pivot
{
    public const GRANTS = ['can_admin', 'can_read', 'can_create', 'can_update', 'can_delete'];

    protected $table = 'cookbook_user';

    public $incrementing = true;

    protected $fillable = [
        'cookbook_id',
        'user_id',
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
