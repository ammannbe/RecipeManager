<?php

namespace App\Announcements;

use App\Households\AccountManagerClient;
use App\Households\AccountManagerUnavailable;
use App\Models\Announcement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Mirrors the announcements of the AccountManager (docs/ANNOUNCEMENTS-API.md there, 3.2): GET /api/announcements
 * with since = server_time of the last successful answer (cache), without since for the first and the daily full
 * sync (copies missing in the full list are withdrawn). Copies are upserted by uuid (unchanged updated_at: nothing
 * written), "deleted" withdraws them (kept hidden, so they are never sent twice). Afterwards push and e-mail of
 * newly published announcements are dispatched (AnnouncementSender).
 */
class AccountManagerAnnouncements
{
    public const SERVER_TIME = 'announcements:server_time';

    public function __construct(private AccountManagerClient $client, private AnnouncementSender $sender) {}

    /**
     * @return array{updated: int, withdrawn: int, full: bool}
     *
     * @throws AccountManagerUnavailable
     */
    public function sync(bool $full = false): array
    {
        // Webhook jobs, the 15-minute and the daily run may meet: one at a time (each one is complete on its own).
        return Cache::lock('announcements:sync', 120)->block(30, function () use ($full): array {
            $since = $full ? null : Cache::get(self::SERVER_TIME);
            $since = is_string($since) && $since !== '' ? $since : null;

            $answer = $this->client->announcements($since);

            $updated = 0;
            $seen = [];

            foreach ($answer['data'] as $item) {
                $data = $this->valid($item);

                if ($data === null) {
                    continue;
                }

                $seen[] = $data['account_manager_uuid'];
                $updated += $this->upsert($data) ? 1 : 0;
            }

            $withdrawn = $this->withdraw(array_map('strtolower', $answer['deleted']));

            if ($since === null) {
                $withdrawn += Announcement::query()
                    ->whereNull('withdrawn_at')
                    ->whereNotIn('account_manager_uuid', $seen)
                    ->update(['withdrawn_at' => now()]);
            }

            Cache::forever(self::SERVER_TIME, $answer['server_time']);

            $this->sender->dispatchPending();

            return ['updated' => $updated, 'withdrawn' => $withdrawn, 'full' => $since === null];
        });
    }

    public static function logUnavailable(AccountManagerUnavailable $exception, string $context): void
    {
        Log::warning("Announcements: {$context} failed, keeping the last state.", ['exception' => $exception->getMessage()]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsert(array $data): bool
    {
        $announcement = Announcement::query()->firstOrNew(['account_manager_uuid' => $data['account_manager_uuid']]);

        $unchanged = $announcement->exists
            && $announcement->withdrawn_at === null
            && $data['remote_updated_at'] instanceof Carbon
            && $announcement->remote_updated_at?->equalTo($data['remote_updated_at']);

        if ($unchanged) {
            return false;
        }

        $announcement->fill($data);
        $announcement->withdrawn_at = null;
        $announcement->save();

        return true;
    }

    /**
     * @param  list<string>  $uuids
     */
    private function withdraw(array $uuids): int
    {
        if ($uuids === []) {
            return 0;
        }

        return Announcement::query()
            ->whereIn('account_manager_uuid', $uuids)
            ->whereNull('withdrawn_at')
            ->update(['withdrawn_at' => now()]);
    }

    /**
     * Validates one announcement of the API; invalid ones are skipped (logged), the rest of the answer applies.
     * Unknown types count as info, unknown channels are dropped, links and images must be http(s).
     *
     * @return array<string, mixed>|null
     */
    private function valid(mixed $item): ?array
    {
        $texts = fn (string $field): array => [
            $field => ['present', 'nullable', 'array'],
            ...collect(Announcement::LOCALES)->mapWithKeys(fn (string $locale) => ["{$field}.{$locale}" => ['nullable', 'string']])->all(),
        ];

        try {
            /** @var array<string, mixed> $data */
            $data = Validator::make(is_array($item) ? $item : [], [
                'uuid' => ['required', 'uuid'],
                'type' => ['required', 'string', 'max:50'],
                ...$texts('title'),
                ...$texts('body_html'),
                'excerpt' => ['nullable', 'array'],
                'channels' => ['present', 'array'],
                'channels.*' => ['string'],
                'link' => ['nullable', 'string'],
                'image_url' => ['nullable', 'string'],
                'publish_at' => ['required', 'date'],
                'expires_at' => ['nullable', 'date'],
                'updated_at' => ['nullable', 'date'],
            ])->validate();
        } catch (ValidationException $exception) {
            Log::warning('Announcements: skipping an invalid announcement of the AccountManager.', [
                'uuid' => is_array($item) ? ($item['uuid'] ?? null) : null,
                'errors' => $exception->errors(),
            ]);

            return null;
        }

        $locales = fn (mixed $values): array => collect(Announcement::LOCALES)
            ->mapWithKeys(fn (string $locale) => [$locale => is_array($values) && is_string($values[$locale] ?? null) && trim($values[$locale]) !== '' ? $values[$locale] : null])
            ->all();

        /** @var list<string> $channels */
        $channels = $data['channels'];

        return [
            'account_manager_uuid' => strtolower((string) $data['uuid']),
            'type' => in_array($data['type'], Announcement::TYPES, true) ? $data['type'] : Announcement::INFO,
            'title' => $locales($data['title']),
            'body_html' => $locales($data['body_html']),
            'excerpt' => $locales($data['excerpt'] ?? null),
            'channels' => array_values(array_unique(array_intersect($channels, Announcement::CHANNELS))),
            'link' => self::httpUrl($data['link'] ?? null),
            'image_url' => self::httpUrl($data['image_url'] ?? null),
            'publish_at' => self::time($data['publish_at']),
            'expires_at' => isset($data['expires_at']) ? self::time($data['expires_at']) : null,
            'remote_updated_at' => isset($data['updated_at']) ? self::time($data['updated_at']) : null,
        ];
    }

    private static function time(mixed $value): Carbon
    {
        return Carbon::parse((string) $value)->setTimezone((string) config('app.timezone'));
    }

    private static function httpUrl(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }
}
