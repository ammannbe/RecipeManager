<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Recipe JSON handed over by a companion app (Shoppinglist). It is imported by the user in the
 * import wizard, where unknown values are resolved by hand: a draft never creates anything itself.
 *
 * @property string $id
 * @property int $user_id
 * @property array<string, mixed> $data
 * @property Carbon $expires_at
 */
class RecipeDraft extends Model
{
    use HasUuids;

    public const LIFETIME_HOURS = 24;

    protected $fillable = [
        'user_id',
        'data',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<RecipeDraft>  $query
     */
    public function scopeUsableBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id)->where('expires_at', '>', now());
    }
}
