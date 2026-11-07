<?php

namespace App\Models;

use App\Enums\HouseholdRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Member of a household mirrored from the AccountManager who has no user here yet (only their
 * oidc_sub is known). Becomes a household member on the first OIDC login with that subject.
 */
class HouseholdPendingMember extends Model
{
    protected $fillable = [
        'household_id',
        'oidc_sub',
        'role',
    ];

    protected $casts = [
        'role' => HouseholdRole::class,
    ];

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
