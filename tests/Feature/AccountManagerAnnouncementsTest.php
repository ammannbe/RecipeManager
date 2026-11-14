<?php

namespace Tests\Feature;

use App\Announcements\AccountManagerAnnouncements;
use App\Announcements\AnnouncementSender;
use App\Filament\Auth\EditProfile;
use App\Filament\Pages\News;
use App\Jobs\SyncAccountManagerAnnouncements;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\User;
use Filament\Facades\Filament;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Minishlink\WebPush\MessageSentReport;
use Mockery;
use NotificationChannels\WebPush\WebPushChannel;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * ANNOUNCEMENTS_DRIVER=account-manager: announcements mirrored from the AccountManager (contract: AccountManager
 * docs/ANNOUNCEMENTS-API.md): sync, webhook, visibility, bell/news/banner, push and e-mail with consent, exactly once,
 * unsubscribing and delivery reports.
 */
class AccountManagerAnnouncementsTest extends TestCase
{
    private const SECRET = 'f00dbabe00000000000000000000000000000000000000000000000000000000';

    private const UUID = '019a8c1e-4b2d-7f3a-9e10-5c6d7e8f9a0b';

    private const OTHER = '019a7b0c-1111-7f3a-9e10-5c6d7e8f9a0b';

    /**
     * Answer of GET /api/announcements: data, deleted, server_time.
     *
     * @var array<string, mixed>
     */
    private array $feed = ['data' => [], 'deleted' => [], 'server_time' => '2026-11-14T10:15:00Z'];

    private int $feedStatus = 200;

    /** @var list<array<string, mixed>> */
    private array $deliveries = [];

    /** @var list<string|null> */
    private array $sinces = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-14 10:15');
        Filament::setCurrentPanel('app');

        config([
            'announcements.driver' => 'account-manager',
            'households.account_manager' => ['url' => 'https://account-manager.test', 'key' => 'recipes', 'secret' => self::SECRET, 'report_shares' => false],
        ]);

        Http::fake(function (HttpRequest $request) {
            $path = Str::after(Str::before($request->url(), '?'), 'https://account-manager.test/api/');

            if ($request->method() === 'GET' && $path === 'announcements') {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $this->sinces[] = isset($query['since']) && is_string($query['since']) ? $query['since'] : null;

                return Http::response($this->feedStatus === 200 ? $this->feed : ['message' => 'Down'], $this->feedStatus);
            }

            if ($request->method() === 'POST' && preg_match('#^announcements/([^/]+)/deliveries$#', $path, $match)) {
                $this->deliveries[] = ['uuid' => $match[1], ...$request->data()];

                return Http::response(['data' => ['channel' => $request['channel'], 'sent' => $request['sent'], 'failed' => $request['failed']]]);
            }

            return Http::response(['message' => 'Not found.'], 404);
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function announcement(array $overrides = []): array
    {
        return [
            'uuid' => self::UUID,
            'type' => 'feature',
            'title' => ['de' => 'Neu: Wochenplan', 'en' => 'New: weekly plan'],
            'body' => ['de' => 'Plane die **Woche**', 'en' => 'Plan your **week**'],
            'body_html' => ['de' => '<p>Plane die <strong>Woche</strong></p>', 'en' => '<p>Plan your <strong>week</strong></p>'],
            'excerpt' => ['de' => 'Plane die Woche', 'en' => 'Plan your week'],
            'channels' => ['in_app', 'banner'],
            'link' => 'https://help.example.com/weekly-plan',
            'image_url' => null,
            'publish_at' => '2026-11-14T09:00:00Z',
            'expires_at' => null,
            'updated_at' => '2026-11-14T09:00:00Z',
            ...$overrides,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @param  list<string>  $deleted
     */
    private function serve(array $data, array $deleted = [], string $serverTime = '2026-11-14T10:15:00Z'): void
    {
        $this->feed = ['data' => $data, 'deleted' => $deleted, 'server_time' => $serverTime];
    }

    private function sync(bool $full = false): void
    {
        $this->command($full ? ['--full' => true] : [])->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function command(array $parameters = []): PendingCommand
    {
        $pending = $this->artisan('account-manager:sync-announcements', $parameters);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(array $attributes = []): User
    {
        return User::factory()->create(['created_at' => '2026-01-01 00:00', 'locale' => 'de', ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return TestResponse<Response>
     */
    private function webhook(array $options = []): TestResponse
    {
        $body = (string) json_encode([
            'event_id' => $options['event_id'] ?? (string) Str::uuid(),
            'type' => 'announcements.changed',
            'occurred_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'data' => ['announcement' => ['uuid' => self::UUID, 'change' => 'published']],
        ], JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->getTimestamp();
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $options['secret'] ?? self::SECRET);

        return $this->call('POST', '/api/account-manager/webhook', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ACCOUNT_MANAGER_EVENT' => 'announcements.changed',
            'HTTP_X_ACCOUNT_MANAGER_TIMESTAMP' => $timestamp,
            'HTTP_X_ACCOUNT_MANAGER_SIGNATURE' => $signature,
        ], content: $body);
    }

    private function enablePush(): void
    {
        config(['webpush.vapid.public_key' => 'BPublicKey', 'webpush.vapid.private_key' => 'private']);
    }

    private function subscribe(User $user): void
    {
        $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/'.$user->getKey(), 'p256dh', 'auth', 'aes128gcm');
    }

    /**
     * Fake WebPushChannel: records the users it sent to, every endpoint succeeds unless listed in $failing.
     *
     * @param  list<int>  $failing  user ids
     * @return object{users: list<int>}
     */
    private function fakePush(array $failing = []): object
    {
        $sent = new class
        {
            /** @var list<int> */
            public array $users = [];
        };

        $channel = Mockery::mock(WebPushChannel::class);
        $channel->shouldReceive('send')->andReturnUsing(function (User $user, $notification) use ($sent, $failing): array {
            $sent->users[] = (int) $user->getKey();
            $message = $notification->toWebPush($user, $notification)->toArray();
            $this->assertSame('Neu: Wochenplan', $message['title']);
            $this->assertSame('Plane die Woche', $message['body']);

            return [new MessageSentReport(new Psr7Request('POST', 'https://fcm.googleapis.com/x'), null, ! in_array($user->getKey(), $failing, true))];
        });
        $this->app->instance(WebPushChannel::class, $channel);

        return $sent;
    }

    // Sync

    public function test_the_first_sync_is_full_and_later_ones_use_the_server_time(): void
    {
        $this->serve([$this->announcement()]);

        $this->sync();

        $this->assertSame([null], $this->sinces);
        $announcement = Announcement::query()->sole();
        $this->assertSame(self::UUID, $announcement->account_manager_uuid);
        $this->assertSame('Neu: Wochenplan', $announcement->title('de'));
        $this->assertSame('New: weekly plan', $announcement->title('en'));
        $this->assertSame(['in_app', 'banner'], $announcement->channels);
        $this->assertTrue($announcement->publish_at->equalTo(Carbon::parse('2026-11-14 09:00')));
        $this->assertSame('2026-11-14T10:15:00Z', Cache::get(AccountManagerAnnouncements::SERVER_TIME));

        $this->serve([], [], '2026-11-14T10:30:00Z');
        $this->sync();

        $this->assertSame([null, '2026-11-14T10:15:00Z'], $this->sinces);
        $this->assertSame('2026-11-14T10:30:00Z', Cache::get(AccountManagerAnnouncements::SERVER_TIME));
    }

    public function test_changes_are_upserted_and_deleted_ones_are_withdrawn(): void
    {
        $this->serve([$this->announcement(), $this->announcement(['uuid' => self::OTHER])]);
        $this->sync();

        $this->serve([$this->announcement(['title' => ['de' => 'Geändert', 'en' => null], 'updated_at' => '2026-11-14T10:20:00Z'])], [self::OTHER]);
        $this->sync();

        $changed = Announcement::query()->where('account_manager_uuid', self::UUID)->sole();
        $this->assertSame('Geändert', $changed->title('de'));
        $this->assertSame('Geändert', $changed->title('en'), 'falls back to the other locale');
        $this->assertNotNull(Announcement::query()->where('account_manager_uuid', self::OTHER)->value('withdrawn_at'));

        // Coming back: visible again, the same row (so it is never sent twice).
        $id = Announcement::query()->where('account_manager_uuid', self::OTHER)->value('id');
        $this->serve([$this->announcement(['uuid' => self::OTHER])]);
        $this->sync();
        $this->assertNull(Announcement::query()->whereKey($id)->value('withdrawn_at'));
        $this->assertSame(2, Announcement::query()->count());
    }

    public function test_the_full_sync_withdraws_copies_missing_in_the_list(): void
    {
        $this->serve([$this->announcement(), $this->announcement(['uuid' => self::OTHER])]);
        $this->sync();

        $this->serve([$this->announcement()]);
        $this->sync(full: true);

        $this->assertSame([null, null], $this->sinces);
        $this->assertNull(Announcement::query()->where('account_manager_uuid', self::UUID)->value('withdrawn_at'));
        $this->assertNotNull(Announcement::query()->where('account_manager_uuid', self::OTHER)->value('withdrawn_at'));
    }

    public function test_an_unchanged_announcement_is_not_written_again(): void
    {
        $this->serve([$this->announcement()]);
        $this->sync();
        Announcement::query()->update(['updated_at' => '2026-01-01 00:00']);

        $this->sync();

        $this->assertSame('2026-01-01 00:00:00', Announcement::query()->sole()->updated_at?->format('Y-m-d H:i:s'));
    }

    public function test_invalid_items_are_skipped_and_unknown_values_normalized(): void
    {
        $this->serve([
            ['uuid' => 'not-a-uuid', 'type' => 'info'],
            $this->announcement(['type' => 'party', 'channels' => ['in_app', 'smoke_signal'], 'link' => 'javascript:alert(1)', 'image_url' => 'ftp://example.com/a.png']),
        ]);

        $this->sync();

        $announcement = Announcement::query()->sole();
        $this->assertSame('info', $announcement->type);
        $this->assertSame(['in_app'], $announcement->channels);
        $this->assertNull($announcement->link);
        $this->assertNull($announcement->image_url);
    }

    public function test_a_failing_account_manager_keeps_the_last_state(): void
    {
        $this->serve([$this->announcement()]);
        $this->sync();

        $this->feedStatus = 503;
        $this->command()->assertFailed();

        $this->assertSame(1, Announcement::query()->live()->count());
        $this->assertSame('2026-11-14T10:15:00Z', Cache::get(AccountManagerAnnouncements::SERVER_TIME));
    }

    public function test_the_none_driver_does_nothing(): void
    {
        config(['announcements.driver' => 'none']);

        $this->command()->assertSuccessful();

        $this->assertSame([], $this->sinces);
    }

    // Webhook

    public function test_the_webhook_triggers_a_queued_sync_once_per_event(): void
    {
        Queue::fake();
        $eventId = (string) Str::uuid();

        $this->webhook(['event_id' => $eventId])->assertNoContent();
        $this->webhook(['event_id' => $eventId])->assertNoContent();

        Queue::assertPushed(SyncAccountManagerAnnouncements::class, 1);
    }

    public function test_the_webhook_syncs_with_the_last_server_time(): void
    {
        Cache::forever(AccountManagerAnnouncements::SERVER_TIME, '2026-11-14T10:00:00Z');
        $this->serve([$this->announcement()]);

        $this->webhook()->assertNoContent();

        $this->assertSame(['2026-11-14T10:00:00Z'], $this->sinces);
        $this->assertSame(1, Announcement::query()->count());
    }

    public function test_the_webhook_rejects_wrong_signatures(): void
    {
        Queue::fake();

        $this->webhook(['secret' => str_repeat('0', 64)])->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_the_webhook_is_closed_without_any_account_manager_feature(): void
    {
        config(['announcements.driver' => 'none']);

        $this->webhook()->assertNotFound();
    }

    // Visibility, bell, news, banner

    public function test_visibility_rules(): void
    {
        $this->serve([
            $this->announcement(),
            $this->announcement(['uuid' => (string) Str::uuid(), 'title' => ['de' => 'Geplant', 'en' => null], 'publish_at' => '2026-11-15T09:00:00Z']),
            $this->announcement(['uuid' => (string) Str::uuid(), 'title' => ['de' => 'Abgelaufen', 'en' => null], 'expires_at' => '2026-11-14T10:00:00Z']),
            $this->announcement(['uuid' => (string) Str::uuid(), 'title' => ['de' => 'Alt', 'en' => null], 'publish_at' => '2026-03-01T09:00:00Z']),
            $this->announcement(['uuid' => (string) Str::uuid(), 'title' => ['de' => 'Alte Wartung', 'en' => null], 'type' => 'maintenance', 'publish_at' => '2026-03-01T09:00:00Z']),
            $this->announcement(['uuid' => (string) Str::uuid(), 'title' => ['de' => 'Nur Push', 'en' => null], 'channels' => ['push']]),
        ]);
        $this->sync();

        $old = $this->user();
        $new = $this->user(['created_at' => '2026-06-01 00:00']);

        $titles = fn (User $user): array => Announcement::query()->visibleTo($user, Announcement::IN_APP)->orderBy('publish_at')->get()->map->title('de')->all();

        $this->assertSame(['Alt', 'Alte Wartung', 'Neu: Wochenplan'], $titles($old));
        // Registered after "Alt" was published: only the maintenance one of before.
        $this->assertSame(['Alte Wartung', 'Neu: Wochenplan'], $titles($new));
    }

    public function test_the_bell_counts_unread_news_and_opening_marks_them_read(): void
    {
        $this->serve([$this->announcement()]);
        $this->sync();
        $user = $this->user();

        $dashboard = (string) $this->actingAs($user)->get(Filament::getPanel('app')->getUrl())->assertOk()->getContent();
        $this->assertStringContainsString('data-announcement-bell', $dashboard);
        $this->assertStringContainsString(News::getUrl(panel: 'app'), $dashboard);

        $news = (string) $this->get(News::getUrl(panel: 'app'))->assertOk()->getContent();
        $this->assertStringContainsString('Neuigkeiten', $news);
        $this->assertStringContainsString('<strong>Woche</strong>', $news);
        $this->assertStringContainsString('data-unread', $news);
        $this->assertNotNull(AnnouncementRead::query()->where('user_id', $user->getKey())->value('read_at'));

        // Read now: no longer highlighted.
        $this->assertStringNotContainsString('data-unread', (string) $this->get(News::getUrl(panel: 'app'))->getContent());
    }

    public function test_english_users_get_the_english_texts(): void
    {
        $this->serve([$this->announcement()]);
        $this->sync();

        $news = (string) $this->actingAs($this->user(['locale' => 'en']))->get(News::getUrl(panel: 'app'))->assertOk()->getContent();

        $this->assertStringContainsString('New: weekly plan', $news);
        $this->assertStringContainsString('<strong>week</strong>', $news);
    }

    public function test_the_banner_shows_until_it_is_closed(): void
    {
        $this->serve([$this->announcement()]);
        $this->sync();
        $user = $this->user();

        $html = (string) $this->actingAs($user)->get(Filament::getPanel('app')->getUrl())->assertOk()->getContent();
        $this->assertStringContainsString('data-announcement-banner="'.self::UUID.'"', $html);
        $this->assertStringContainsString('Plane die Woche', $html);

        $announcement = Announcement::query()->sole();
        $this->post(route('announcements.dismiss', $announcement))->assertRedirect();

        $read = AnnouncementRead::query()->where('user_id', $user->getKey())->sole();
        $this->assertNotNull($read->dismissed_at);
        $this->assertStringNotContainsString('data-announcement-banner', (string) $this->get(Filament::getPanel('app')->getUrl())->getContent());
    }

    public function test_nothing_is_shown_with_the_none_driver(): void
    {
        $this->serve([$this->announcement()]);
        $this->sync();
        config(['announcements.driver' => 'none']);
        $user = $this->user();

        $html = (string) $this->actingAs($user)->get(Filament::getPanel('app')->getUrl())->assertOk()->getContent();

        $this->assertStringNotContainsString('data-announcement-bell', $html);
        $this->assertStringNotContainsString('data-announcement-banner', $html);
        $this->get(News::getUrl(panel: 'app'))->assertForbidden();
    }

    // E-mail

    public function test_news_mails_go_only_to_verified_users_who_opted_in(): void
    {
        Mail::fake();
        $optedIn = $this->user(['announcement_emails' => true]);
        $this->user();
        $this->user(['announcement_emails' => true, 'email_verified_at' => null]);
        $this->user(['announcement_emails' => true, 'created_at' => '2026-11-14 09:30']);

        $this->serve([$this->announcement(['channels' => ['email']])]);
        $this->sync();

        Mail::assertSent(AnnouncementMail::class, 1);
        Mail::assertSent(AnnouncementMail::class, fn (AnnouncementMail $mail): bool => $mail->hasTo($optedIn->email));
        $this->assertSame([['uuid' => self::UUID, 'channel' => 'email', 'sent' => 1, 'failed' => 0, 'batch' => "email-{$optedIn->getKey()}-{$optedIn->getKey()}"]], $this->deliveries);
    }

    public function test_maintenance_mails_go_to_everybody_until_unsubscribed(): void
    {
        Mail::fake();
        $default = $this->user();
        $unsubscribed = $this->user(['maintenance_emails' => false]);
        $newcomer = $this->user(['created_at' => '2026-11-14 09:30']);

        $this->serve([$this->announcement(['type' => 'maintenance', 'channels' => ['email']])]);
        $this->sync();

        Mail::assertSent(AnnouncementMail::class, 2);
        Mail::assertSent(AnnouncementMail::class, fn (AnnouncementMail $mail): bool => $mail->hasTo($default->email));
        Mail::assertSent(AnnouncementMail::class, fn (AnnouncementMail $mail): bool => $mail->hasTo($newcomer->email));
        Mail::assertNotSent(AnnouncementMail::class, fn (AnnouncementMail $mail): bool => $mail->hasTo($unsubscribed->email));
    }

    public function test_the_mail_is_localized_and_has_an_unsubscribe_link(): void
    {
        $user = $this->user(['announcement_emails' => true, 'locale' => 'en']);
        $this->serve([$this->announcement(['channels' => ['email']])]);
        $this->sync();
        $announcement = Announcement::query()->sole();

        $mail = (new AnnouncementMail($announcement, $user))->locale('en');
        $mail->assertHasSubject('New: weekly plan');
        $mail->assertSeeInHtml('Plan your <strong', false);
        $mail->assertSeeInHtml('week</strong>', false);
        $mail->assertSeeInHtml('https://help.example.com/weekly-plan');

        $headers = $mail->headers()->text;
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
        $this->assertStringContainsString('/announcements/unsubscribe/'.$user->getKey().'/news', $headers['List-Unsubscribe']);
        $this->assertStringContainsString('signature=', $headers['List-Unsubscribe']);
    }

    public function test_unsubscribing_needs_a_valid_signature_and_a_confirmation(): void
    {
        $user = $this->user(['announcement_emails' => true]);
        $url = URL::signedRoute('announcements.unsubscribe', ['user' => $user->getKey(), 'kind' => 'news']);

        $this->get($url)->assertOk()->assertSee('Unsubscribe');
        $this->assertTrue($user->refresh()->announcement_emails);

        $this->post($url.'x')->assertForbidden();
        $this->assertTrue($user->refresh()->announcement_emails);

        // One-click (RFC 8058) without CSRF token.
        $this->post($url)->assertOk()->assertSee('data-unsubscribed', false);
        $this->assertFalse($user->refresh()->announcement_emails);
        $this->assertTrue($user->refresh()->maintenance_emails);

        $maintenance = URL::signedRoute('announcements.unsubscribe', ['user' => $user->getKey(), 'kind' => 'maintenance']);
        $this->post($maintenance)->assertOk();
        $this->assertFalse($user->refresh()->maintenance_emails);
    }

    public function test_each_announcement_is_mailed_once(): void
    {
        Mail::fake();
        $user = $this->user(['announcement_emails' => true]);
        $this->serve([$this->announcement(['channels' => ['email']])]);

        $this->sync();
        $this->sync(full: true);
        $this->serve([$this->announcement(['channels' => ['email'], 'updated_at' => '2026-11-14T10:10:00Z'])]);
        $this->sync();

        // A retried or overlapping batch skips the users handled already.
        $sender = app(AnnouncementSender::class);
        $sender->sendBatch(Announcement::EMAIL, Announcement::query()->sole()->getKey(), [$user->getKey()]);

        Mail::assertSent(AnnouncementMail::class, 1);
        $this->assertSame(1, DB::table('announcement_deliveries')->where('channel', 'email')->count());
        // The retried batch reports the same numbers again (same batch id: replaced in the AccountManager).
        $this->assertCount(2, $this->deliveries);
        $this->assertSame($this->deliveries[0], $this->deliveries[1]);
    }

    public function test_old_and_expired_announcements_are_not_sent(): void
    {
        Mail::fake();
        $this->user(['announcement_emails' => true, 'created_at' => '2025-01-01 00:00']);

        $this->serve([$this->announcement(['channels' => ['email'], 'publish_at' => '2026-10-01T09:00:00Z'])]);
        $this->sync();

        Mail::assertNothingSent();
        $this->assertNotNull(Announcement::query()->sole()->email_dispatched_at);
    }

    public function test_a_batch_after_the_expiry_sends_nothing(): void
    {
        Mail::fake();
        $user = $this->user(['announcement_emails' => true]);
        $this->serve([$this->announcement(['channels' => ['in_app'], 'expires_at' => '2026-11-14T11:00:00Z'])]);
        $this->sync();
        Carbon::setTestNow('2026-11-14 11:30');

        app(AnnouncementSender::class)->sendBatch(Announcement::EMAIL, Announcement::query()->sole()->getKey(), [$user->getKey()]);

        Mail::assertNothingSent();
    }

    // Push

    public function test_push_goes_only_to_users_with_a_subscription(): void
    {
        $this->enablePush();
        $push = $this->fakePush();
        $subscribed = $this->user();
        $this->subscribe($subscribed);
        $this->user();

        $this->serve([$this->announcement(['channels' => ['push']])]);
        $this->sync();
        $this->sync(full: true);

        $this->assertSame([$subscribed->getKey()], $push->users);
        $this->assertSame([['uuid' => self::UUID, 'channel' => 'push', 'sent' => 1, 'failed' => 0, 'batch' => "push-{$subscribed->getKey()}-{$subscribed->getKey()}"]], $this->deliveries);
    }

    public function test_failed_pushes_are_reported(): void
    {
        $this->enablePush();
        $ok = $this->user();
        $failing = $this->user();
        $this->subscribe($ok);
        $this->subscribe($failing);
        $this->fakePush(failing: [$failing->getKey()]);

        $this->serve([$this->announcement(['channels' => ['push']])]);
        $this->sync();

        $this->assertSame(1, $this->deliveries[0]['sent']);
        $this->assertSame(1, $this->deliveries[0]['failed']);
    }

    public function test_without_vapid_keys_push_waits(): void
    {
        $push = $this->fakePush();
        $this->subscribe($this->user());

        $this->serve([$this->announcement(['channels' => ['push']])]);
        $this->sync();

        $this->assertSame([], $push->users);
        $this->assertNull(Announcement::query()->sole()->push_dispatched_at);

        // Set up later (within the send window): sent with the next sync.
        $this->enablePush();
        $this->sync();
        $this->assertCount(1, $push->users);
    }

    public function test_devices_subscribe_only_with_known_push_services(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->postJson(route('push-subscriptions.store'), [
            'endpoint' => 'https://evil.example.com/push',
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ])->assertUnprocessable();

        $this->postJson(route('push-subscriptions.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
            'content_encoding' => 'aes128gcm',
        ])->assertNoContent();
        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertNoContent();
        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    // Profile

    public function test_the_profile_stores_the_consents(): void
    {
        $user = $this->user();
        $this->enablePush();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->assertSee(__('News by e-mail'))
            ->assertSee(__('Push notifications on this device'))
            ->fillForm(['announcement_emails' => true, 'maintenance_emails' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($user->refresh()->announcement_emails);
        $this->assertFalse($user->refresh()->maintenance_emails);
    }

    public function test_the_profile_hides_the_consents_with_the_none_driver(): void
    {
        config(['announcements.driver' => 'none']);
        $this->actingAs($this->user());

        Livewire::test(EditProfile::class)->assertDontSee(__('News by e-mail'));
    }
}
