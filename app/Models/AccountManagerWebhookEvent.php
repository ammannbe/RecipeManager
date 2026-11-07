<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Webhook event of the AccountManager that was accepted already: repeated deliveries are
 * acknowledged without applying them again. Kept for 7 days (model:prune).
 */
class AccountManagerWebhookEvent extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $primaryKey = 'event_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'event_id',
        'type',
        'created_at',
    ];

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(7));
    }
}
