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
 * After the import the draft remembers the recipe (`recipe_id`, `imported_at`) and is kept for
 * KEEP_IMPORTED_DAYS, so the companion app can link its entry (GET /api/recipe-drafts/{draft}).
 *
 * @property string $id
 * @property int $user_id
 * @property array<string, mixed> $data
 * @property int|null $recipe_id
 * @property Carbon|null $imported_at
 * @property Carbon $expires_at
 * @property-read Recipe|null $recipe
 */
class RecipeDraft extends Model
{
    use HasUuids;

    public const LIFETIME_HOURS = 24;

    /** Imported drafts are kept this long after the import (the companion app asks for the recipe). */
    public const KEEP_IMPORTED_DAYS = 30;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'data',
        'recipe_id',
        'imported_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'imported_at' => 'datetime',
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
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Drafts the user can still import: own, not expired, not imported yet.
     *
     * @param  Builder<RecipeDraft>  $query
     */
    public function scopeUsableBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id)->where('expires_at', '>', now())->whereNull('imported_at');
    }

    public function status(): string
    {
        return match (true) {
            $this->imported_at !== null => self::STATUS_IMPORTED,
            $this->expires_at->isPast() => self::STATUS_EXPIRED,
            default => self::STATUS_PENDING,
        };
    }

    /**
     * Marks the draft as imported into the recipe (the wizard). Only once: a second import of
     * the same draft (e.g. another tab) does not overwrite the first recipe.
     */
    public static function markImported(string $id, Recipe $recipe): void
    {
        self::query()->whereKey($id)->whereNull('imported_at')->update([
            'recipe_id' => $recipe->id,
            'imported_at' => now(),
        ]);
    }

    /**
     * Removes expired drafts that were not imported, and imported ones after KEEP_IMPORTED_DAYS.
     *
     * @return int number of removed drafts
     */
    public static function prune(): int
    {
        return self::query()
            ->where(fn (Builder $query) => $query->whereNull('imported_at')->where('expires_at', '<=', now()))
            ->orWhere('imported_at', '<=', now()->subDays(self::KEEP_IMPORTED_DAYS))
            ->delete();
    }
}
