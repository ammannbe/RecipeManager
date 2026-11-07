<?php

namespace Tests\Feature;

use App\Entitlements\Entitlements;
use App\Filament\Auth\Login;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\AccountManagerWebhookEvent;
use App\Models\Author;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\CookbookSharing;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Concerns\MocksOidcProvider;
use Tests\TestCase;

/**
 * ENTITLEMENTS_DRIVER / AUTH_ADMIN_SOURCE=account-manager and user.deleted (contract: AccountManager
 * docs/ENTITLEMENTS-API.md).
 */
class AccountManagerEntitlementsTest extends TestCase
{
    use MocksOidcProvider;

    private const SECRET = 'f00dbabe00000000000000000000000000000000000000000000000000000000';

    /**
     * Answers of the fake AccountManager: "GET entitlements/<sub>" => [status, body] or a closure.
     *
     * @var array<string, array{0: int, 1: array<mixed>}|Closure>
     */
    private array $accountManager = [];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00');

        config([
            'entitlements.driver' => 'account-manager',
            'entitlements.admin_source' => 'account-manager',
            'households.account_manager' => ['url' => 'https://account-manager.test', 'key' => 'recipes', 'secret' => self::SECRET, 'report_shares' => false],
            'login.oidc.enabled' => true,
            'login.oidc.registration' => true,
            'login.oidc.required_group' => null,
        ]);

        Http::fake(function (HttpRequest $request) {
            $route = $request->method().' '.Str::after($request->url(), 'https://account-manager.test/api/');
            $answer = $this->accountManager[$route] ?? [404, ['message' => 'Not found.']];

            if ($answer instanceof Closure) {
                return $answer($request);
            }

            return Http::response($answer[1], $answer[0]);
        });

        $this->user = User::factory()->linked('sub-123')->create(['email' => 'cook@example.com']);
    }

    /**
     * @param  list<string>  $features
     * @return array{sub: string, service: string, status: string, admin: bool, features: list<string>, version: int}
     */
    private function snapshot(int $version, string $status = 'active', bool $admin = false, array $features = [], string $sub = 'sub-123'): array
    {
        return ['sub' => $sub, 'service' => 'recipes', 'status' => $status, 'admin' => $admin, 'features' => $features, 'version' => $version];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     * @return TestResponse<Response>
     */
    private function webhook(string $type, array $data, array $options = []): TestResponse
    {
        $body = (string) json_encode([
            'event_id' => $options['event_id'] ?? (string) Str::uuid(),
            'type' => $type,
            'occurred_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES);
        $timestamp = (string) ($options['timestamp'] ?? now()->getTimestamp());
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $options['secret'] ?? self::SECRET);

        return $this->call('POST', '/api/account-manager/webhook', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ACCOUNT_MANAGER_EVENT' => $type,
            'HTTP_X_ACCOUNT_MANAGER_TIMESTAMP' => $timestamp,
            'HTTP_X_ACCOUNT_MANAGER_SIGNATURE' => $options['signature'] ?? $signature,
        ], content: $body);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $options
     * @return TestResponse<Response>
     */
    private function changed(array $snapshot, array $options = []): TestResponse
    {
        return $this->webhook('entitlements.changed', ['entitlements' => $snapshot], $options);
    }

    private function stored(?User $user = null): ?UserEntitlement
    {
        return UserEntitlement::query()->where('user_id', ($user ?? $this->user)->getKey())->first();
    }

    private function store(User $user, string $status, bool $admin = false, int $version = 1): void
    {
        UserEntitlement::query()->create(['user_id' => $user->getKey(), 'status' => $status, 'admin' => $admin, 'features' => [], 'version' => $version]);
        $user->unsetRelation('entitlement');
    }

    private function command(string $command): PendingCommand
    {
        $pending = $this->artisan($command);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    public function test_the_webhook_is_closed_without_an_account_manager_integration(): void
    {
        config(['entitlements.driver' => 'local', 'entitlements.admin_source' => 'local']);

        $this->changed($this->snapshot(1))->assertNotFound();

        config(['entitlements.admin_source' => 'account-manager', 'households.account_manager.secret' => null]);

        $this->changed($this->snapshot(1))->assertNotFound();
        $this->assertNull($this->stored());
    }

    public function test_invalid_signatures_and_foreign_services_are_rejected(): void
    {
        $this->changed($this->snapshot(1), ['secret' => str_repeat('0', 64)])->assertUnauthorized();
        $this->changed($this->snapshot(1), ['timestamp' => now()->getTimestamp() - 301])->assertUnauthorized();
        $this->changed([...$this->snapshot(1), 'service' => 'shoppinglist'])->assertUnprocessable();
        $this->changed([...$this->snapshot(1), 'version' => 'x'])->assertUnprocessable();

        $this->assertNull($this->stored());
        $this->assertSame(0, AccountManagerWebhookEvent::query()->count());
    }

    public function test_snapshots_are_applied_once_and_in_version_order(): void
    {
        $eventId = (string) Str::uuid();

        $this->changed($this->snapshot(2, admin: true, features: ['recipes.ai', 'recipes.ai']), ['event_id' => $eventId])->assertNoContent();

        $stored = $this->stored();
        $this->assertSame(['active', true, ['recipes.ai'], 2], [$stored?->status, $stored?->admin, $stored?->features, $stored?->version]);
        $this->assertNull($stored?->synced_at);

        // The same event again (other content) and older or equal versions are acknowledged, not applied.
        $this->changed($this->snapshot(5, 'suspended'), ['event_id' => $eventId])->assertNoContent();
        $this->changed($this->snapshot(1, 'suspended'))->assertNoContent();
        $this->changed($this->snapshot(2, 'suspended'))->assertNoContent();
        $this->assertSame('active', $this->stored()?->status);

        // A newer snapshot replaces everything; admin and features only count while active.
        $this->changed($this->snapshot(3, 'suspended', admin: true, features: ['recipes.ai']))->assertNoContent();
        $stored = $this->stored();
        $this->assertSame(['suspended', false, [], 3], [$stored->status, $stored->admin, $stored->features, $stored->version]);

        // Unknown statuses count as "none".
        $this->changed($this->snapshot(4, 'paused'))->assertNoContent();
        $this->assertSame('none', $this->stored()?->status);
    }

    public function test_events_of_unknown_subjects_and_unknown_types_are_acknowledged(): void
    {
        $this->changed($this->snapshot(1, sub: 'sub-unknown'))->assertNoContent();
        $this->webhook('subscription.renewed', ['foo' => 'bar'])->assertNoContent();

        $this->assertSame(0, UserEntitlement::query()->count());
        Http::assertNothingSent();
    }

    public function test_features_follow_the_driver(): void
    {
        config(['entitlements.local.features' => ['recipes.extra']]);
        $local = User::factory()->create();

        // Never synced: no features; unlinked users keep the local ones.
        $this->assertFalse(Entitlements::has($this->user, 'recipes.ai'));
        $this->assertNull(Entitlements::status($this->user));
        $this->assertTrue(Entitlements::has($local, 'recipes.extra'));
        $this->assertSame('active', Entitlements::status($local));

        $this->changed($this->snapshot(1, features: ['recipes.ai']))->assertNoContent();
        $this->user->refresh();
        $this->assertTrue(Entitlements::has($this->user, 'recipes.ai'));
        $this->assertFalse(Entitlements::has($this->user, 'recipes.extra'));

        config(['entitlements.driver' => 'local']);
        $this->assertSame(['recipes.extra'], Entitlements::features($this->user));
        $this->assertSame('active', Entitlements::status($this->user));
    }

    public function test_admin_rights_follow_the_admin_source(): void
    {
        $emergency = User::factory()->admin()->create();
        $linkedLocalAdmin = User::factory()->linked('sub-local-admin')->admin()->create();
        $this->changed($this->snapshot(1, admin: true))->assertNoContent();
        $this->user->refresh();

        $this->assertTrue($this->user->isAdmin());
        $this->assertTrue($emergency->isAdmin());
        // The local flag of a linked user does not count, nor does a missing snapshot.
        $this->assertFalse($linkedLocalAdmin->isAdmin());
        $this->assertEqualsCanonicalizing([$this->user->id, $emergency->id], User::query()->admins()->pluck('id')->all());

        // Policies and admin pages use it.
        $this->assertTrue($this->user->can('viewAny', Author::class));
        $this->actingAs($this->user)->get(ListAuthors::getUrl())->assertOk();
        $this->actingAs($linkedLocalAdmin)->get(ListAuthors::getUrl())->assertForbidden();

        $this->changed($this->snapshot(2, admin: false))->assertNoContent();
        $this->assertFalse($this->user->refresh()->isAdmin());

        config(['entitlements.admin_source' => 'local']);
        $this->assertTrue($linkedLocalAdmin->isAdmin());
        $this->assertFalse($this->user->isAdmin());
        $this->assertEqualsCanonicalizing([$linkedLocalAdmin->id, $emergency->id], User::query()->admins()->pluck('id')->all());
    }

    public function test_a_blocked_user_is_signed_out_and_the_api_refuses(): void
    {
        $this->store($this->user, 'suspended');

        $this->actingAs($this->user)->get(Filament::getPanel('app')->getUrl())
            ->assertRedirect(Filament::getPanel('app')->getLoginUrl(['access' => 'suspended']));
        $this->assertGuest();
        Livewire::withQueryParams(['access' => 'suspended'])->test(Login::class)->assertNotified('Your access to '.config('app.name').' is suspended.');

        // The public frontend signs out as well.
        $this->actingAs($this->user)->get('/')->assertRedirect(Filament::getPanel('app')->getLoginUrl(['access' => 'suspended']));
        $this->assertGuest();

        Passport::actingAs($this->user);
        $this->getJson('/api/user')->assertForbidden()->assertJson(['message' => 'Your access to '.config('app.name').' is suspended.']);
    }

    public function test_requested_and_removed_access_is_not_enabled(): void
    {
        foreach (['requested', 'none'] as $status) {
            UserEntitlement::query()->delete();
            $this->store($this->user, $status);

            $this->actingAs($this->user)->get(Filament::getPanel('app')->getUrl())
                ->assertRedirect(Filament::getPanel('app')->getLoginUrl(['access' => 'disabled']));
            $this->assertGuest();
        }

        Livewire::withQueryParams(['access' => 'disabled'])->test(Login::class)->assertNotified('Your access to '.config('app.name').' is not enabled.');
        Livewire::withQueryParams(['access' => 'other'])->test(Login::class)->assertNotNotified();
    }

    public function test_emergency_admins_and_the_local_driver_are_not_blocked(): void
    {
        $emergency = User::factory()->admin()->create();

        $this->actingAs($emergency)->get(Filament::getPanel('app')->getUrl())->assertOk();
        $this->assertTrue($emergency->isAdmin());

        // Never synced (e.g. the AccountManager was down at the first sign-in): not locked out.
        $this->actingAs($this->user)->get(Filament::getPanel('app')->getUrl())->assertOk();

        $this->store($this->user, 'suspended');
        config(['entitlements.driver' => 'local']);
        $this->actingAs($this->user)->get(Filament::getPanel('app')->getUrl())->assertOk();
        Passport::actingAs($this->user);
        $this->getJson('/api/user')->assertOk();
    }

    public function test_the_sign_in_fetches_the_snapshot(): void
    {
        $this->accountManager['GET entitlements/sub-123'] = [200, ['data' => $this->snapshot(4, admin: true, features: ['recipes.ai'])]];

        $this->signIn()->assertRedirect(Filament::getPanel('app')->getUrl());

        $this->assertAuthenticatedAs($this->user);
        $stored = $this->stored();
        $this->assertSame(['active', true, 4], [$stored?->status, $stored?->admin, $stored?->version]);
        $this->assertNotNull($stored?->synced_at);
        $this->assertTrue($this->user->refresh()->isAdmin());
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://account-manager.test/api/entitlements/sub-123'
            && $request->header('Authorization') === ['Basic '.base64_encode('recipes:'.self::SECRET)]);
    }

    public function test_a_blocked_or_unknown_user_cannot_sign_in(): void
    {
        $this->accountManager['GET entitlements/sub-123'] = [200, ['data' => $this->snapshot(4, 'suspended')]];

        $this->signIn()->assertRedirect(Filament::getPanel('app')->getLoginUrl());
        $this->assertGuest();
        Notification::assertNotified('Your access to '.config('app.name').' is suspended.');

        // 404: no access (anymore), the version stays.
        $this->accountManager['GET entitlements/sub-123'] = [404, ['message' => 'Not found.']];

        $this->signIn()->assertRedirect(Filament::getPanel('app')->getLoginUrl());
        $this->assertGuest();
        Notification::assertNotified('Your access to '.config('app.name').' is not enabled.');
        $this->assertSame(['none', 4], [$this->stored()?->status, $this->stored()?->version]);
        $this->assertSame(1, User::query()->where('oidc_sub', 'sub-123')->count());
    }

    public function test_the_sign_in_works_with_the_last_state_while_the_account_manager_is_down(): void
    {
        $this->store($this->user, 'active', admin: true, version: 3);
        $this->accountManager['GET entitlements/sub-123'] = fn () => Http::failedConnection();
        $log = Log::spy();

        $this->signIn()->assertRedirect(Filament::getPanel('app')->getUrl());

        $this->assertAuthenticatedAs($this->user);
        $this->assertSame(['active', true, 3], [$this->stored()?->status, $this->stored()?->admin, $this->stored()?->version]);
        $log->shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'entitlements on sign-in'))->once();
    }

    public function test_the_sync_command_fetches_all_linked_users(): void
    {
        $other = User::factory()->linked('sub-other')->create();
        User::factory()->create();
        $this->store($other, 'active', admin: true, version: 7);
        $this->accountManager['GET entitlements/sub-123'] = [200, ['data' => $this->snapshot(2, features: ['recipes.ai'])]];

        $this->command('entitlements:sync')->expectsOutputToContain('2 user(s)')->assertSuccessful();

        $this->assertSame(['active', ['recipes.ai'], 2], [$this->stored()?->status, $this->stored()?->features, $this->stored()?->version]);
        $this->assertSame(['none', false, 7], [$this->stored($other)?->status, $this->stored($other)?->admin, $this->stored($other)?->version]);
        Http::assertSentCount(2);

        // API answers apply the same version, but not older ones.
        $this->accountManager['GET entitlements/sub-123'] = [200, ['data' => $this->snapshot(2, 'suspended')]];
        $this->accountManager['GET entitlements/sub-other'] = [200, ['data' => $this->snapshot(6, sub: 'sub-other')]];
        $this->command('entitlements:sync')->assertSuccessful();
        $this->assertSame('suspended', $this->stored()?->status);
        $this->assertSame('none', $this->stored($other)?->status);
    }

    public function test_the_last_state_stays_while_the_account_manager_is_down(): void
    {
        $this->store($this->user, 'active', admin: true, version: 3);
        $this->accountManager['GET entitlements/sub-123'] = [503, ['message' => 'Down']];

        $this->command('entitlements:sync')->assertFailed();

        $this->assertSame(['active', true, 3], [$this->stored()?->status, $this->stored()?->admin, $this->stored()?->version]);

        config(['entitlements.driver' => 'local', 'entitlements.admin_source' => 'local']);
        $this->command('entitlements:sync')->expectsOutputToContain('not synced')->assertSuccessful();
    }

    public function test_unlinking_or_relinking_drops_the_snapshot(): void
    {
        $this->store($this->user, 'active', admin: true, version: 9);

        $this->user->forceFill(['oidc_sub' => 'sub-new'])->save();

        $this->assertNull($this->stored());
        $this->assertFalse($this->user->isAdmin());
    }

    public function test_user_deleted_deletes_the_user_and_their_private_contents(): void
    {
        $other = User::factory()->create();
        $own = Cookbook::factory()->create(['author_id' => $this->user->author_id, 'name' => 'Privat']);
        $ownRecipe = Recipe::factory()->create(['cookbook_id' => $own->id, 'author_id' => $this->user->author_id, 'name' => 'Privates Rezept']);
        $guestRecipe = Recipe::factory()->create(['cookbook_id' => $own->id, 'author_id' => $other->author_id, 'name' => 'Gast im eigenen Kochbuch']);
        $loose = Recipe::factory()->create(['cookbook_id' => null, 'author_id' => $this->user->author_id, 'name' => 'Ohne Kochbuch']);
        $foreign = Cookbook::factory()->create(['author_id' => $other->author_id, 'name' => 'Geteilt']);
        app(CookbookSharing::class)->addMember($foreign, $this->user, ['can_read' => true, 'can_create' => true]);
        $contributed = Recipe::factory()->create(['cookbook_id' => $foreign->id, 'author_id' => $this->user->author_id, 'name' => 'Beitrag']);
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Shoppinglist', ['https://shopping.test/callback']);
        DB::table('oauth_access_tokens')->insert(['id' => Str::random(80), 'user_id' => $this->user->id, 'client_id' => $client->getKey(), 'revoked' => false, 'created_at' => now()]);
        RecipeDraft::query()->create(['user_id' => $this->user->id, 'data' => [], 'expires_at' => now()->addDay()]);
        $this->store($this->user, 'active');
        $authorId = $this->user->author_id;

        $this->webhook('user.deleted', ['user' => ['sub' => 'sub-123']])->assertNoContent();

        $this->assertNull(User::withTrashed()->find($this->user->id));
        $this->assertNull(Author::withTrashed()->find($authorId));
        $this->assertNull(Cookbook::withTrashed()->find($own->id));
        $this->assertSame(0, Recipe::withTrashed()->whereKey([$ownRecipe->id, $guestRecipe->id, $loose->id])->count());
        $this->assertSame(0, UserEntitlement::query()->count());
        $this->assertSame(0, RecipeDraft::query()->count());
        $this->assertSame(0, DB::table('oauth_access_tokens')->where('user_id', $this->user->id)->count());
        // Shared contents stay: the contribution to another user's cookbook moves to its author.
        $this->assertSame($other->author_id, $contributed->fresh()?->author_id);
        $this->assertNotNull($foreign->fresh());
        $this->assertSame(0, $foreign->members()->count());

        // Repeated delivery and unknown subjects: acknowledged, nothing happens.
        $this->webhook('user.deleted', ['user' => ['sub' => 'sub-123']])->assertNoContent();
        $this->webhook('user.deleted', ['user' => ['sub' => 'sub-unknown']])->assertNoContent();
        $this->assertNotNull($other->fresh());
    }

    public function test_user_deleted_never_touches_users_without_a_linked_sign_in(): void
    {
        $emergency = User::factory()->admin()->create();

        $this->webhook('user.deleted', ['user' => ['sub' => '']])->assertUnprocessable();

        $this->assertNotNull($emergency->fresh());
    }
}
