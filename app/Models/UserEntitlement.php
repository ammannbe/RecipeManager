<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Last entitlements snapshot of the AccountManager for a linked user (see App\Entitlements\Entitlements).
 *
 * @property string $status
 * @property bool $admin
 * @property list<string> $features
 * @property int $version
 */
class UserEntitlement extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'admin',
        'features',
        'version',
        'synced_at',
    ];

    protected $casts = [
        'admin' => 'boolean',
        'features' => 'array',
        'version' => 'integer',
        'synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
