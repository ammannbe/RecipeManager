<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Local copy of an announcement of the AccountManager (docs/ANNOUNCEMENTS-API.md there). Texts are stored per
 * locale (de, en; null when empty) and shown in the app locale with the other one as fallback. body_html comes
 * sanitized from the AccountManager.
 *
 * @property int $id
 * @property string $account_manager_uuid
 * @property string $type
 * @property array<string, string|null> $title
 * @property array<string, string|null> $body_html
 * @property array<string, string|null> $excerpt
 * @property list<string> $channels
 * @property string|null $link
 * @property string|null $image_url
 * @property Carbon $publish_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $remote_updated_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $push_dispatched_at
 * @property Carbon|null $email_dispatched_at
 */
class Announcement extends Model
{
    public const FEATURE = 'feature';

    public const INFO = 'info';

    public const MAINTENANCE = 'maintenance';

    public const TYPES = [self::FEATURE, self::INFO, self::MAINTENANCE];

    public const IN_APP = 'in_app';

    public const BANNER = 'banner';

    public const PUSH = 'push';

    public const EMAIL = 'email';

    public const CHANNELS = [self::IN_APP, self::BANNER, self::PUSH, self::EMAIL];

    public const LOCALES = ['de', 'en'];

    protected $guarded = ['id'];

    protected $casts = [
        'title' => 'array',
        'body_html' => 'array',
        'excerpt' => 'array',
        'channels' => 'array',
        'publish_at' => 'datetime',
        'expires_at' => 'datetime',
        'remote_updated_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'push_dispatched_at' => 'datetime',
        'email_dispatched_at' => 'datetime',
    ];

    /**
     * @return HasMany<AnnouncementRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * Published, not expired and not withdrawn.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('withdrawn_at')
            ->where('publish_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Live announcements of a channel the user may see: users do not see announcements published before
     * they registered, except maintenance ones.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, string $channel): void
    {
        $query->live()
            ->whereJsonContains('channels', $channel)
            ->where(fn (Builder $query) => $query
                ->where('type', self::MAINTENANCE)
                ->orWhere('publish_at', '>=', $user->created_at ?? Carbon::create(1970)));
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    public function isMaintenance(): bool
    {
        return $this->type === self::MAINTENANCE;
    }

    public function title(?string $locale = null): string
    {
        return (string) $this->localized($this->title, $locale);
    }

    public function bodyHtml(?string $locale = null): string
    {
        return (string) $this->localized($this->body_html, $locale);
    }

    public function excerpt(?string $locale = null): string
    {
        return (string) $this->localized($this->excerpt, $locale);
    }

    /**
     * The value in the locale (default: the app locale), otherwise in the other one.
     *
     * @param  array<string, string|null>  $values
     */
    private function localized(array $values, ?string $locale): ?string
    {
        $language = strtolower(substr($locale ?? app()->getLocale(), 0, 2));

        foreach ([$language, ...self::LOCALES] as $candidate) {
            if (filled($values[$candidate] ?? null)) {
                return $values[$candidate];
            }
        }

        return null;
    }
}
