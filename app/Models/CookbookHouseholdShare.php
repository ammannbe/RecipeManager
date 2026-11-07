<?php

namespace App\Models;

use App\Households\AccountManagerHouseholds;
use App\Jobs\ReportAccountManagerShares;
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

    protected static function booted(): void
    {
        // The AccountManager shows the cookbooks shared with a mirrored household (HOUSEHOLDS_DRIVER=account-manager).
        static::created(fn (CookbookHouseholdShare $share) => $share->reportToAccountManager());
        static::deleted(fn (CookbookHouseholdShare $share) => $share->reportToAccountManager());
    }

    private function reportToAccountManager(): void
    {
        if (AccountManagerHouseholds::enabled() && ($household = Household::query()->find($this->getAttribute('household_id'))) !== null) {
            ReportAccountManagerShares::dispatchFor($household);
        }
    }
}
