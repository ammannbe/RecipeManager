<?php

namespace App\Models;

use Database\Factories\CookbookInvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CookbookInvitation extends Model
{
    /** @use HasFactory<CookbookInvitationFactory> */
    use HasFactory;

    public const GRANTS = ['can_admin', 'can_read', 'can_create', 'can_update', 'can_delete'];

    protected $fillable = [
        'cookbook_id',
        'invited_by_user_id',
        'email',
        'can_admin',
        'can_read',
        'can_create',
        'can_update',
        'can_delete',
        'token_hash',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'can_admin' => 'boolean',
        'can_read' => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function newToken(): string
    {
        return Str::random(64);
    }

    /**
     * @return BelongsTo<Cookbook, $this>
     */
    public function cookbook(): BelongsTo
    {
        return $this->belongsTo(Cookbook::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return array<string, bool>
     */
    public function grants(): array
    {
        return array_map(fn (string $grant): bool => (bool) $this->{$grant}, array_combine(self::GRANTS, self::GRANTS));
    }
}
