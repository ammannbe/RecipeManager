<?php

namespace Tests\Feature;

use App\Enums\HouseholdRole;
use App\Filament\Auth\EditProfile;
use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Filament\Resources\Households\Pages\ViewHousehold;
use App\Jobs\ApplyKontoEvent;
use App\Models\Cookbook;
use App\Models\CookbookHouseholdShare;
use App\Models\Household;
use App\Models\HouseholdPendingMember;
use App\Models\KontoWebhookEvent;
use App\Models\Recipe;
use App\Models\User;
use App\Services\CookbookSharing;
use Closure;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * HOUSEHOLDS_DRIVER=konto: households mirrored from the AccountManager (contract: AccountManager
 * docs/HOUSEHOLDS-API.md).
 */
class KontoHouseholdTest extends TestCase
{
    private const SECRET = 'f00dbabe00000000000000000000000000000000000000000000000000000000';

    private const UUID = '019a5e3c-6f1d-7b2a-9c43-0d8e5f6a7b8c';

    /**
     * Answers of the fake AccountManager: "GET households/<uuid>" => [status, body] or a closure.
     *
     * @var array<string, array{0: int, 1: array<mixed>}|Closure>
     */
    private array $konto = [];

    private User $owner;

    private User $member;

    private Cookbook $cookbook;

    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00');

        config([
            'households.driver' => 'konto',
            'households.konto' => ['url' => 'https://konto.test', 'key' => 'recipes', 'secret' => self::SECRET, 'report_shares' => false],
        ]);

        Http::fake(function (HttpRequest $request) {
            $route = $request->method().' '.Str::after($request->url(), 'https://konto.test/api/');
            $answer = $this->konto[$route] ?? [404, ['message' => 'Not found.']];

            if ($answer instanceof Closure) {
                return $answer($request);
            }

            return Http::response($answer[1], $answer[0]);
        });

        $this->owner = User::factory()->create(['admin' => false]);
        $this->owner->forceFill(['oidc_sub' => 'sub-owner'])->save();
        $this->member = User::factory()->create(['admin' => false]);
        $this->member->forceFill(['oidc_sub' => 'sub-member'])->save();

        $this->cookbook = Cookbook::factory()->create(['author_id' => $this->owner->author_id, 'is_public' => false, 'name' => 'Familienrezepte']);
        $this->recipe = Recipe::factory()->create([
            'cookbook_id' => $this->cookbook->id,
            'author_id' => $this->owner->author_id,
            'is_public' => false,
            'name' => 'Household soup',
        ]);
    }

    /**
     * @param  array<string, string>  $members
     * @return array{uuid: string, name: string, version: int, members: list<array{sub: string, role: string}>}
     */
    private function snapshot(int $version, array $members = ['sub-owner' => 'admin', 'sub-member' => 'member'], string $name = 'Familie Muster', string $uuid = self::UUID): array
    {
        return [
            'uuid' => $uuid,
            'name' => $name,
            'version' => $version,
            'members' => array_map(fn (string $sub, string $role): array => ['sub' => $sub, 'role' => $role], array_keys($members), $members),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $options
     * @return TestResponse<Response>
     */
    private function webhook(string $type, array $snapshot, array $options = []): TestResponse
    {
        $body = (string) json_encode([
            'event_id' => $options['event_id'] ?? (string) Str::uuid(),
            'type' => $type,
            'occurred_at' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'data' => ['household' => $snapshot, ...($options['data'] ?? [])],
        ], JSON_UNESCAPED_SLASHES);
        $timestamp = (string) ($options['timestamp'] ?? now()->getTimestamp());
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $options['secret'] ?? self::SECRET);

        return $this->call('POST', '/api/konto/webhook', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_KONTO_EVENT' => $type,
            'HTTP_X_KONTO_TIMESTAMP' => $timestamp,
            'HTTP_X_KONTO_SIGNATURE' => $options['signature'] ?? $signature,
        ], content: $body);
    }

    /**
     * The household arrives with household.created (unknown: confirmed through the API).
     *
     * @param  array{uuid: string, name: string, version: int, members: list<array{sub: string, role: string}>}  $snapshot
     */
    private function mirror(array $snapshot): Household
    {
        $this->konto['GET households/'.$snapshot['uuid']] = [200, ['data' => $snapshot]];
        $this->webhook('household.created', $snapshot)->assertNoContent();

        return Household::query()->where('konto_household_uuid', $snapshot['uuid'])->firstOrFail();
    }

    /**
     * @param  array<string, bool>  $grants
     */
    private function share(Household $household, ?Cookbook $cookbook = null, array $grants = ['can_read' => true], ?User $by = null): void
    {
        $this->assertTrue(app(CookbookSharing::class)->shareWithHousehold($cookbook ?? $this->cookbook, $household, $grants, $by ?? $this->owner));
    }

    private function canRead(User $user, ?Recipe $recipe = null): bool
    {
        $recipe ??= $this->recipe;
        $visible = Recipe::query()->visibleTo($user)->whereKey($recipe->id)->exists();

        $this->assertSame($visible, $user->fresh()?->can('view', $recipe));

        return $visible;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function command(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    public function test_events_of_unknown_households_are_confirmed_through_the_api(): void
    {
        $household = $this->mirror($this->snapshot(1, ['sub-owner' => 'admin', 'sub-member' => 'member', 'sub-unknown' => 'member']));

        $this->assertSame('Familie Muster', $household->name);
        $this->assertSame(1, $household->konto_version);
        $this->assertNull($household->created_by);
        $this->assertSame(HouseholdRole::Admin, $household->roleOf($this->owner));
        $this->assertSame(HouseholdRole::Member, $household->roleOf($this->member));
        $this->assertSame(['sub-unknown'], $household->pendingMembers()->pluck('oidc_sub')->all());
        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://konto.test/api/households/'.self::UUID
            && $request->header('Authorization') === ['Basic '.base64_encode('recipes:'.self::SECRET)]
            && $request->header('Accept') === ['application/json']);

        // An event after the deletion (404) creates nothing.
        $this->konto['GET households/'.self::UUID] = [404, ['message' => 'Not found.']];
        $this->webhook('household.deleted', [...$this->snapshot(2, []), 'deleted' => true])->assertNoContent();
        $this->webhook('household.member_added', $this->snapshot(1))->assertNoContent();

        $this->assertSame(0, Household::query()->count());
        $this->assertSame(0, HouseholdPendingMember::query()->count());
    }

    public function test_invalid_and_expired_signatures_are_rejected(): void
    {
        $snapshot = $this->snapshot(1);
        $this->konto['GET households/'.self::UUID] = [200, ['data' => $snapshot]];

        $this->webhook('household.created', $snapshot, ['secret' => str_repeat('0', 64)])->assertUnauthorized();
        $this->webhook('household.created', $snapshot, ['signature' => ''])->assertUnauthorized();
        $this->webhook('household.created', $snapshot, ['timestamp' => now()->getTimestamp() - 301])->assertUnauthorized();
        $this->webhook('household.created', $snapshot, ['timestamp' => now()->getTimestamp() + 301])->assertUnauthorized();
        $this->webhook('household.created', $snapshot, ['timestamp' => 'abc'])->assertUnauthorized();
        $this->postJson('/api/konto/webhook', ['event_id' => (string) Str::uuid(), 'type' => 'household.created', 'data' => ['household' => $snapshot]])->assertUnauthorized();

        $this->assertSame(0, Household::query()->count());
        $this->assertSame(0, KontoWebhookEvent::query()->count());
        Http::assertNothingSent();

        // Within the tolerance, and the signed body has to be valid.
        $this->webhook('household.created', ['uuid' => 'nope'])->assertUnprocessable();
        $this->webhook('household.created', $snapshot, ['timestamp' => now()->getTimestamp() - 299])->assertNoContent();
        $this->assertSame(1, Household::query()->count());
    }

    public function test_the_local_driver_rejects_webhooks(): void
    {
        config(['households.driver' => 'local']);
        $this->webhook('household.created', $this->snapshot(1))->assertNotFound();

        config(['households.driver' => 'konto', 'households.konto.secret' => null]);
        $this->webhook('household.created', $this->snapshot(1))->assertNotFound();

        $this->assertSame(0, Household::query()->count());
        Http::assertNothingSent();
    }

    public function test_events_are_applied_once(): void
    {
        $this->mirror($this->snapshot(1));
        Queue::fake();
        $eventId = (string) Str::uuid();

        $this->webhook('household.updated', $this->snapshot(2, name: 'Muster'), ['event_id' => $eventId])->assertNoContent();
        $this->webhook('household.updated', $this->snapshot(2, name: 'Muster'), ['event_id' => strtoupper($eventId)])->assertNoContent();

        Queue::assertPushed(ApplyKontoEvent::class, 1);
        $this->assertSame(2, KontoWebhookEvent::query()->count());

        // Unknown types are acknowledged; with a household they count as household.updated.
        $this->webhook('user.deleted', [], ['data' => ['household' => null]])->assertNoContent();
        Queue::assertPushed(ApplyKontoEvent::class, 1);

        $this->travel(8)->days();
        $this->command('model:prune', ['--model' => [KontoWebhookEvent::class]])->assertSuccessful()->run();
        $this->assertSame(0, KontoWebhookEvent::query()->count());
    }

    public function test_stale_versions_are_ignored(): void
    {
        $household = $this->mirror($this->snapshot(3));
        $this->share($household);

        $this->webhook('household.updated', $this->snapshot(3, name: 'Gleich'))->assertNoContent();
        $this->webhook('household.member_removed', $this->snapshot(2, ['sub-owner' => 'admin']))->assertNoContent();

        $this->assertSame('Familie Muster', $household->fresh()?->name);
        $this->assertTrue($household->hasMember($this->member));
        $this->assertTrue($this->canRead($this->member));

        // Unknown types with a household are applied like household.updated.
        $this->webhook('household.archived', $this->snapshot(4, name: 'Familie Meier'))->assertNoContent();

        $household->refresh();
        $this->assertSame('Familie Meier', $household->name);
        $this->assertSame(4, $household->konto_version);
    }

    public function test_member_changes_grant_and_revoke_access_to_shared_cookbooks(): void
    {
        $household = $this->mirror($this->snapshot(1));
        $this->share($household, grants: ['can_read' => true, 'can_update' => true]);

        $this->assertTrue($this->canRead($this->member));
        $this->assertTrue($this->member->can('update', $this->recipe));
        $this->assertTrue(Cookbook::query()->visibleTo($this->member)->whereKey($this->cookbook->id)->exists());
        $this->actingAs($this->member)->get(route('recipes.show', $this->recipe))->assertOk();

        // Removed: access is gone right away, the cookbook stays with its owner.
        $this->webhook('household.member_removed', $this->snapshot(2, ['sub-owner' => 'admin']), ['data' => ['member' => ['sub' => 'sub-member', 'role' => 'member']]])->assertNoContent();

        $this->assertFalse($household->hasMember($this->member));
        $this->assertFalse($this->canRead($this->member));
        $this->assertFalse($this->member->fresh()?->can('update', $this->recipe));
        $this->assertFalse(Cookbook::query()->visibleTo($this->member)->whereKey($this->cookbook->id)->exists());
        $this->actingAs($this->member)->get(route('recipes.show', $this->recipe))->assertNotFound();
        $this->assertTrue($this->canRead($this->owner));

        // Added again (as admin), then the role changes; cookbook access does not depend on the role.
        $this->webhook('household.member_added', $this->snapshot(3, ['sub-owner' => 'admin', 'sub-member' => 'admin']))->assertNoContent();
        $this->assertSame(HouseholdRole::Admin, $household->roleOf($this->member));
        $this->assertTrue($this->canRead($this->member));

        $this->webhook('household.member_updated', $this->snapshot(4, ['sub-owner' => 'admin', 'sub-member' => 'member']), ['data' => ['member' => ['sub' => 'sub-member', 'role' => 'member']]])->assertNoContent();
        $this->assertSame(HouseholdRole::Member, $household->roleOf($this->member));
        $this->assertTrue($this->canRead($this->member));

        // A new member who uses the app already gets access right away.
        $newcomer = User::factory()->create(['admin' => false]);
        $newcomer->forceFill(['oidc_sub' => 'sub-newcomer'])->save();
        $this->assertFalse($this->canRead($newcomer));

        $this->webhook('household.member_added', $this->snapshot(5, ['sub-owner' => 'admin', 'sub-member' => 'member', 'sub-newcomer' => 'member']))->assertNoContent();

        $this->assertTrue($this->canRead($newcomer));
        $this->assertSame(3, $household->users()->count());
    }

    public function test_removed_members_keep_their_own_cookbooks(): void
    {
        $household = $this->mirror($this->snapshot(1));
        $own = Cookbook::factory()->create(['author_id' => $this->member->author_id, 'is_public' => false]);
        $ownRecipe = Recipe::factory()->create(['cookbook_id' => $own->id, 'author_id' => $this->member->author_id, 'is_public' => false]);
        $this->share($household, $own, by: $this->member);

        $this->webhook('household.member_removed', $this->snapshot(2, ['sub-owner' => 'admin']))->assertNoContent();

        // Like leaving a local household: the share stays until its owner removes it.
        $this->assertTrue($this->canRead($this->member, $ownRecipe));
        $this->assertTrue($own->households()->whereKey($household->id)->exists());
    }

    public function test_a_deleted_household_removes_only_the_shares(): void
    {
        $household = $this->mirror($this->snapshot(1, ['sub-owner' => 'admin', 'sub-member' => 'member', 'sub-later' => 'member']));
        $this->share($household);
        $this->assertTrue($this->canRead($this->member));

        $this->webhook('household.deleted', [...$this->snapshot(2, []), 'deleted' => true])->assertNoContent();

        $this->assertModelMissing($household);
        $this->assertSame(0, CookbookHouseholdShare::query()->count());
        $this->assertSame(0, HouseholdPendingMember::query()->count());
        $this->assertFalse($this->canRead($this->member));
        $this->assertTrue($this->canRead($this->owner));
        $this->assertModelExists($this->cookbook);
        $this->assertModelExists($this->recipe);
    }

    /**
     * @return TestResponse<Response>
     */
    private function oidcSignIn(string $sub, string $email): TestResponse
    {
        config(['login.oidc.enabled' => true, 'login.oidc.registration' => true, 'login.oidc.required_group' => null]);

        $identity = (new SocialiteUser)->setRaw(['sub' => $sub, 'email' => $email, 'email_verified' => true, 'name' => 'Neu'])
            ->map(['id' => $sub, 'email' => $email, 'name' => 'Neu', 'nickname' => null]);
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($identity);
        Socialite::shouldReceive('driver')->with('oidc')->andReturn($provider);

        return $this->get(route('oidc.callback'));
    }

    public function test_pending_members_are_attached_on_their_first_oidc_sign_in(): void
    {
        $snapshot = $this->snapshot(1, ['sub-owner' => 'admin', 'sub-new' => 'member']);
        $household = $this->mirror($snapshot);
        $this->share($household);
        $this->assertSame(1, $household->pendingMembers()->count());
        $this->konto['GET households?sub=sub-new'] = [200, ['data' => [$snapshot]]];

        $this->oidcSignIn('sub-new', 'neu@example.com')->assertRedirect();

        $user = User::query()->where('oidc_sub', 'sub-new')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(HouseholdRole::Member, $household->roleOf($user));
        $this->assertSame(0, $household->pendingMembers()->count());
        $this->assertTrue($this->canRead($user));
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://konto.test/api/households?sub=sub-new');
    }

    public function test_linking_the_sign_in_attaches_pending_memberships(): void
    {
        $local = User::factory()->create(['admin' => false, 'email' => 'linked@example.com']);
        $household = $this->mirror($this->snapshot(1, ['sub-owner' => 'admin', 'sub-linked' => 'admin']));
        $this->share($household);
        $this->konto['GET households?sub=sub-linked'] = [200, ['data' => [$this->snapshot(1, ['sub-owner' => 'admin', 'sub-linked' => 'admin'])]]];

        $this->actingAs($local)->withSession(['oidc.link' => true]);
        $this->oidcSignIn('sub-linked', 'other@example.com')->assertRedirect();

        $this->assertSame('sub-linked', $local->fresh()?->oidc_sub);
        $this->assertSame(HouseholdRole::Admin, $household->roleOf($local));
        $this->assertTrue($this->canRead($local));
    }

    public function test_the_sign_in_works_while_the_konto_is_down(): void
    {
        $household = $this->mirror($this->snapshot(1, ['sub-owner' => 'admin', 'sub-new' => 'member']));
        $this->konto['GET households?sub=sub-new'] = fn () => Http::failedConnection();
        $log = Log::spy();

        $this->oidcSignIn('sub-new', 'neu@example.com')->assertRedirect();

        $user = User::query()->where('oidc_sub', 'sub-new')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        // The pending membership is known locally.
        $this->assertTrue($household->hasMember($user));
        $log->shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'on sign-in'))->once();
    }

    public function test_unlinking_the_sign_in_leaves_the_mirrored_households(): void
    {
        $household = $this->mirror($this->snapshot(1));
        $this->share($household);

        $this->actingAs($this->member);
        Livewire::test(EditProfile::class)->callAction('unlinkOidc');

        $this->assertNull($this->member->fresh()?->oidc_sub);
        $this->assertFalse($household->hasMember($this->member));
        $this->assertSame(['sub-member'], $household->pendingMembers()->pluck('oidc_sub')->all());
        $this->assertFalse($this->canRead($this->member));
    }

    public function test_the_sync_command_mirrors_all_households(): void
    {
        $kept = $this->mirror($this->snapshot(1));
        $left = $this->mirror($this->snapshot(1, ['sub-owner' => 'admin'], 'Ferienhaus', '019a5e3c-0000-7000-8000-000000000002'));
        $gone = $this->mirror($this->snapshot(1, ['sub-member' => 'admin'], 'WG', '019a5e3c-0000-7000-8000-000000000003'));
        $this->share($left);
        $this->share($gone, by: $this->member, grants: ['can_read' => true]);
        Http::fake(); // only resets the recorded requests, the fake AccountManager stays

        $this->konto = [
            'GET households?sub=sub-owner' => [200, ['data' => [
                $this->snapshot(2, name: 'Familie Meier'),
                $this->snapshot(1, ['sub-owner' => 'member', 'sub-later' => 'admin'], 'Neu', '019a5e3c-0000-7000-8000-000000000004'),
            ]]],
            'GET households?sub=sub-member' => [200, ['data' => [$this->snapshot(2, name: 'Familie Meier')]]],
            // The owner left: the household no longer lists them → checked one by one.
            'GET households/019a5e3c-0000-7000-8000-000000000002' => [200, ['data' => $this->snapshot(2, ['sub-other' => 'admin'], 'Ferienhaus', '019a5e3c-0000-7000-8000-000000000002')]],
            'GET households/019a5e3c-0000-7000-8000-000000000003' => [404, ['message' => 'Not found.']],
        ];

        $this->command('households:sync')->assertSuccessful()->run();

        $kept->refresh();
        $this->assertSame('Familie Meier', $kept->name);
        $this->assertSame(2, $kept->konto_version);
        $this->assertFalse($left->hasMember($this->owner));
        $this->assertSame(['sub-other'], $left->pendingMembers()->pluck('oidc_sub')->all());
        $this->assertModelMissing($gone);
        $new = Household::query()->where('konto_household_uuid', '019a5e3c-0000-7000-8000-000000000004')->firstOrFail();
        $this->assertSame(HouseholdRole::Member, $new->roleOf($this->owner));
        $this->assertSame(['sub-later'], $new->pendingMembers()->pluck('oidc_sub')->all());
        // Every household is fetched once.
        Http::assertSentCount(4);
    }

    public function test_api_answers_apply_the_same_version_but_not_older_ones(): void
    {
        $household = $this->mirror($this->snapshot(2));
        $household->forceFill(['name' => 'Lokal verändert'])->save();

        $this->konto['GET households?sub=sub-owner'] = [200, ['data' => [$this->snapshot(2)]]];
        $this->konto['GET households?sub=sub-member'] = [200, ['data' => [$this->snapshot(1, ['sub-member' => 'admin'], 'Alt')]]];

        $this->command('households:sync')->assertSuccessful()->run();

        $this->assertSame('Familie Muster', $household->fresh()?->name);
        $this->assertSame(HouseholdRole::Admin, $household->roleOf($this->owner));
    }

    public function test_the_last_state_stays_while_the_konto_is_down(): void
    {
        $household = $this->mirror($this->snapshot(1));
        $this->share($household);
        $this->konto = [
            'GET households?sub=sub-owner' => fn () => Http::failedConnection(),
            'GET households?sub=sub-member' => [503, []],
        ];
        $log = Log::spy();

        $this->command('households:sync')->assertFailed()->run();

        $this->assertTrue($household->fresh()?->hasMember($this->member));
        $this->assertTrue($this->canRead($this->member));
        $log->shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'households:sync'))->once();

        // An event of an unknown household cannot be confirmed: nothing is created, the job retries.
        $this->webhook('household.created', $this->snapshot(1, uuid: '019a5e3c-0000-7000-8000-000000000009'))->assertNoContent();
        $this->assertSame(1, Household::query()->count());
    }

    public function test_local_households_are_proposed_and_mapped(): void
    {
        $local = User::factory()->create(['admin' => false]);
        // Mirrored already (the event came before the poll): the second proposal is merged into it.
        $mirrored = $this->mirror($this->snapshot(1, ['sub-owner' => 'admin'], 'Ferienhaus', '019a5e3c-0000-7000-8000-000000000002'));
        $this->share($mirrored, grants: ['can_read' => true]);

        config(['households.driver' => 'local']);
        $family = Household::factory()->administeredBy($this->owner)->withMember($this->member)->withMember($local)->create(['name' => 'Familie']);
        $holiday = Household::factory()->administeredBy($this->owner)->create(['name' => 'Ferien']);
        $nobody = Household::factory()->administeredBy($local)->create(['name' => 'Ohne SSO']);
        $this->share($family);
        $this->share($holiday, grants: ['can_read' => true, 'can_update' => true]);
        $baking = Cookbook::factory()->create(['author_id' => $this->owner->author_id, 'is_public' => false]);
        $this->share($holiday, $baking);
        $this->assertTrue($this->canRead($local));
        config(['households.driver' => 'konto']);

        $pending = [
            ['local_id' => (string) $family->id, 'status' => 'pending', 'household_uuid' => null, 'household' => null],
            ['local_id' => (string) $holiday->id, 'status' => 'pending', 'household_uuid' => null, 'household' => null],
        ];
        $this->konto['POST households/import'] = [202, ['data' => $pending]];
        $this->konto['GET households/import'] = [200, ['data' => $pending]];

        $this->command('households:import')->assertSuccessful()->run();

        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST' && $request->data() === ['households' => [
            ['local_id' => (string) $family->id, 'name' => 'Familie', 'members' => [['sub' => 'sub-owner', 'role' => 'admin'], ['sub' => 'sub-member', 'role' => 'member']]],
            ['local_id' => (string) $holiday->id, 'name' => 'Ferien', 'members' => [['sub' => 'sub-owner', 'role' => 'admin']]],
        ]]);
        $this->assertSame('pending', $family->fresh()?->konto_import_status);
        $this->assertNull($nobody->fresh()?->konto_import_status);

        $this->actingAs($this->owner);
        Livewire::test(ListHouseholds::class)
            ->assertSee(__('Proposed for your account, waiting for confirmation'))
            ->assertSee(__('Managed in your account'));

        // The admin of the AccountManager confirmed both.
        $this->konto['GET households/import'] = [200, ['data' => [
            ['local_id' => (string) $family->id, 'status' => 'confirmed', 'household_uuid' => self::UUID, 'household' => $this->snapshot(1, name: 'Familie Muster')],
            ['local_id' => (string) $holiday->id, 'status' => 'confirmed', 'household_uuid' => '019a5e3c-0000-7000-8000-000000000002', 'household' => $this->snapshot(2, ['sub-owner' => 'admin'], 'Ferienhaus', '019a5e3c-0000-7000-8000-000000000002')]],
        ]];

        $this->command('households:import', ['--check' => true])->assertSuccessful()->run();

        $family->refresh();
        $this->assertSame(self::UUID, $family->konto_household_uuid);
        $this->assertSame('Familie Muster', $family->name);
        $this->assertNull($family->konto_import_status);
        $this->assertSame(1, $family->konto_version);
        $this->assertTrue($family->hasMember($this->member));
        $this->assertTrue($this->canRead($this->member));
        // Members without a linked sign-in are not in the AccountManager's household.
        $this->assertFalse($family->hasMember($local));
        $this->assertFalse($this->canRead($local));

        // Merged: the shares moved over, grants add up.
        $this->assertModelMissing($holiday);
        $this->assertEqualsCanonicalizing([$this->cookbook->id, $baking->id], $mirrored->cookbooks()->pluck('cookbooks.id')->all());
        $this->assertTrue((bool) $mirrored->cookbooks()->whereKey($this->cookbook->id)->firstOrFail()->getRelationValue('pivot')->can_update);
        $this->assertSame(2, $mirrored->fresh()?->konto_version);

        // Nothing pending anymore: the hourly check does not call the AccountManager.
        Http::fake(); // only resets the recorded requests
        $this->command('households:import', ['--check' => true])->assertSuccessful()->run();
        Http::assertNothingSent();
    }

    public function test_rejected_proposals_stay_local_and_read_only(): void
    {
        $family = Household::factory()->administeredBy($this->owner)->withMember($this->member)->create(['name' => 'Familie']);
        $this->share($family);
        $family->forceFill(['konto_import_status' => 'pending'])->save();
        $this->konto['GET households/import'] = [200, ['data' => [
            ['local_id' => (string) $family->id, 'status' => 'rejected', 'household_uuid' => null, 'household' => null],
        ]]];

        $this->command('households:import', ['--check' => true])->assertSuccessful()->run();

        $family->refresh();
        $this->assertSame('rejected', $family->konto_import_status);
        $this->assertNull($family->konto_household_uuid);
        $this->assertTrue($this->canRead($this->member));
        $this->assertFalse($this->owner->can('update', $family));

        $this->actingAs($this->owner);
        Livewire::test(ListHouseholds::class)->assertSee(__('Not taken over into your account'));
    }

    public function test_the_import_needs_the_konto_driver(): void
    {
        config(['households.driver' => 'local']);

        $this->command('households:import')->assertFailed()->run();
        $this->command('households:sync')->assertSuccessful()->run();

        Http::assertNothingSent();
    }

    public function test_shares_are_reported_to_the_konto(): void
    {
        config(['households.konto.report_shares' => true]);
        $household = $this->mirror($this->snapshot(1));
        $shares = [];
        $this->konto['PUT households/'.self::UUID.'/shares'] = function (HttpRequest $request) use (&$shares) {
            $shares[] = $request->data()['shares'];

            return Http::response(['data' => ['shares' => $request->data()['shares']]]);
        };

        $this->share($household);
        $this->cookbook->update(['name' => 'Backen']);
        $this->cookbook->update(['is_public' => true]);
        $this->cookbook->households()->detach($household->id);

        $this->assertSame([
            [['resource_type' => 'cookbook', 'resource_id' => (string) $this->cookbook->id, 'label' => 'Familienrezepte']],
            [['resource_type' => 'cookbook', 'resource_id' => (string) $this->cookbook->id, 'label' => 'Backen']],
            [],
        ], $shares);

        // The overview is optional: failures do not break sharing, the daily sync reports again.
        $this->konto['PUT households/'.self::UUID.'/shares'] = [500, ['message' => 'Server Error']];
        $this->share($household);
        $this->assertTrue($this->cookbook->households()->whereKey($household->id)->exists());
        $this->assertSame(hash('sha256', '[]'), $household->fresh()?->konto_shares_hash);
    }

    public function test_the_households_page_links_to_the_konto(): void
    {
        $household = $this->mirror($this->snapshot(1, ['sub-owner' => 'admin', 'sub-new' => 'member', 'sub-other' => 'member']));

        $this->actingAs($this->owner);

        Livewire::test(ListHouseholds::class)
            ->assertCanSeeTableRecords([$household])
            ->assertActionVisible('manageInKonto')
            ->assertActionHasUrl('manageInKonto', 'https://konto.test/households')
            ->assertActionHidden('create')
            ->assertTableColumnStateSet('pending_members_count', 2, $household)
            ->assertSee(__('Managed in your account'));

        Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
            ->assertActionVisible('manageInKonto')
            ->assertActionHidden('edit')
            ->assertActionHidden('delete')
            ->assertActionHidden('leave')
            ->assertSee(__('Not using the app yet'));

        $this->assertFalse($this->owner->can('manageMembers', $household));
    }

    public function test_switching_back_to_local_keeps_the_households_and_shares(): void
    {
        $household = $this->mirror($this->snapshot(1, ['sub-owner' => 'admin', 'sub-member' => 'member', 'sub-new' => 'member']));
        $this->share($household);

        config(['households.driver' => 'local']);

        $this->assertFalse($household->fresh()?->isMirrored());
        $this->assertTrue($this->owner->can('update', $household));
        $this->assertTrue($this->owner->can('manageMembers', $household));
        $this->assertTrue($this->canRead($this->member));
        $this->webhook('household.deleted', [...$this->snapshot(2, []), 'deleted' => true])->assertNotFound();
        $this->assertModelExists($household);

        $this->actingAs($this->owner);
        Livewire::test(ListHouseholds::class)
            ->assertActionHidden('manageInKonto')
            ->assertActionVisible('create');
    }
}
