<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lookup entries (foods, units, attributes, tags) a non-admin requested while importing:
 * pending while requested_at is set. Option lists use visibleTo(), so a pending entry is
 * only offered to its requester (and admins); recipes using it show it to everyone.
 *
 * @property int|null $requested_by
 * @property Carbon|null $requested_at
 */
trait Requestable
{
    public function initializeRequestable(): void
    {
        $this->mergeCasts(['requested_at' => 'datetime']);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Approved entries, plus the user's own requests; admins see all.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->admin) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('requested_at'))
            ->when($user !== null, fn (Builder $q) => $q->orWhere($this->qualifyColumn('requested_by'), $user->getKey())));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRequested(Builder $query): Builder
    {
        return $query->whereNotNull($this->qualifyColumn('requested_at'));
    }

    public function isRequested(): bool
    {
        return $this->requested_at !== null;
    }

    public function approve(): void
    {
        $this->forceFill(['requested_by' => null, 'requested_at' => null])->save();
    }
}
