<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Households\AccountManagerClient;
use App\Households\AccountManagerUsers;
use App\Jobs\ReportAccountManagerUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;
use Tests\Feature\Concerns\MocksOidcProvider;
use Tests\TestCase;

/**
 * ACCOUNT_MANAGER_REPORT_USERS: this app reports its users to the AccountManager (contract: AccountManager
 * docs/SERVICE-USERS-API.md).
 */
class AccountManagerUsersTest extends TestCase
{
    use MocksOidcProvider;

    private const URL = 'https://account-manager.test/api/service-users';

    /**
     * HTTP status the fake AccountManager answers with.
     */
    private int $status = 200;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-14 10:00');
        Filament::setCurrentPanel('app');

        config([
            'households.account_manager' => [
                'url' => 'https://account-manager.test',
                'key' => 'recipes',
                'secret' => 'secret',
                'report_shares' => false,
                'report_users' => true,
            ],
            'login.oidc.enabled' => true,
            'login.oidc.registration' => true,
            'login.oidc.required_group' => null,
        ]);

        Http::fake(function (HttpRequest $request) {
            if ($this->status !== 200) {
                return Http::response(['message' => 'down'], $this->status);
            }

            return match ($request->method()) {
                'PUT' => Http::response(['upserted' => count($request->data()['users'] ?? []), 'removed' => 1]),
                'DELETE' => Http::response(null, 204),
                default => Http::response(['ok' => true]),
            };
        });
    }

    /**
     * @return list<HttpRequest>
     */
    private function requests(string $method): array
    {
        return array_values(array_map(
            fn (array $pair): HttpRequest => $pair[0],
            Http::recorded(fn (HttpRequest $request): bool => $request->method() === $method)->all(),
        ));
    }

    private function report(): PendingCommand
    {
        $command = $this->artisan('account-manager:report-users');
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    public function test_the_payload_follows_the_contract(): void
    {
        $user = User::factory()->linked()->create(['email' => 'anna@example.test', 'last_login_at' => now()]);
        $user->author->update(['name' => 'Anna']);

        $this->assertSame([
            'id' => (string) $user->id,
            'email' => 'anna@example.test',
            'name' => 'Anna',
            'oidc_sub' => $user->oidc_sub,
            'last_login_at' => '2026-11-14T10:00:00+00:00',
            'created_at' => '2026-11-14T10:00:00+00:00',
        ], AccountManagerUsers::payload($user->fresh() ?? $user));
    }

    public function test_a_new_user_is_reported_with_basic_auth(): void
    {
        $user = User::factory()->create(['email' => 'new@example.test']);

        $posts = $this->requests('POST');
        $this->assertNotEmpty($posts);
        $this->assertSame(self::URL, $posts[0]->url());
        $this->assertSame('new@example.test', $posts[0]['email']);
        $this->assertSame((string) $user->id, $posts[0]['id']);
        $this->assertSame('Basic '.base64_encode('recipes:secret'), $posts[0]->header('Authorization')[0]);
    }

    public function test_local_registration_is_reported(): void
    {
        Notification::fake();

        $component = Livewire::test(Register::class)->fillForm([
            'name' => 'New Cook',
            'email' => 'new.cook@example.com',
            'password' => 'secret-password-123',
            'passwordConfirmation' => 'secret-password-123',
        ]);
        $this->travel(Register::MIN_SECONDS_TO_SUBMIT)->seconds();
        $component->call('register')->assertHasNoFormErrors();

        $this->assertTrue(collect($this->requests('POST'))->contains(fn (HttpRequest $request): bool => $request['email'] === 'new.cook@example.com' && $request['name'] === 'New Cook'));
    }

    public function test_an_oidc_sign_in_reports_the_link_and_the_last_login(): void
    {
        $user = User::factory()->create(['email' => 'cook@example.com', 'email_verified_at' => now()]);

        $this->signIn()->assertRedirect();

        $user->refresh();
        $this->assertSame('sub-123', $user->oidc_sub);
        $this->assertNotNull($user->last_login_at);

        $last = collect($this->requests('POST'))->last();
        $this->assertNotNull($last);
        $this->assertSame('sub-123', $last['oidc_sub']);
        $this->assertSame('2026-11-14T10:00:00+00:00', $last['last_login_at']);
    }

    public function test_email_and_author_name_changes_are_reported(): void
    {
        $user = User::factory()->create();
        $before = count($this->requests('POST'));

        $user->update(['email' => 'changed@example.test']);
        $user->author->update(['name' => 'Renamed Cook']);

        $posts = array_slice($this->requests('POST'), $before);
        $this->assertCount(2, $posts);
        $this->assertSame('changed@example.test', $posts[0]['email']);
        $this->assertSame('Renamed Cook', $posts[1]['name']);
    }

    public function test_unrelated_changes_are_not_reported(): void
    {
        $user = User::factory()->create();
        $before = count($this->requests('POST'));

        $user->update(['locale' => 'de', 'ai_enabled' => false]);

        $this->assertCount($before, $this->requests('POST'));
    }

    public function test_soft_and_force_deletion_are_reported_as_deletion(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $deletes = $this->requests('DELETE');
        $this->assertNotEmpty($deletes);
        $this->assertSame(self::URL.'/'.$user->id, $deletes[0]->url());

        $user->forceDelete();
        $this->assertGreaterThan(count($deletes), count($this->requests('DELETE')));
    }

    public function test_restoring_reports_the_user_again(): void
    {
        $user = User::factory()->create();
        $user->delete();
        $before = count($this->requests('POST'));

        $user->restore();

        $this->assertCount($before + 1, $this->requests('POST'));
    }

    public function test_nothing_is_reported_while_disabled(): void
    {
        config(['households.account_manager.report_users' => false]);

        User::factory()->create()->delete();
        $this->report()->expectsOutputToContain('not reported')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_reports_are_queued_after_commit(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        Queue::assertPushed(ReportAccountManagerUser::class, fn (ReportAccountManagerUser $job): bool => $job->userId === $user->id);
    }

    public function test_a_failing_account_manager_never_breaks_the_sign_in(): void
    {
        $this->status = 503;
        User::factory()->linked()->create(['oidc_sub' => 'sub-123', 'email' => 'cook@example.com']);

        $this->signIn()->assertRedirect(Filament::getPanel('app')->getUrl());

        $this->assertAuthenticated();
    }

    public function test_a_failed_job_is_released_for_a_retry(): void
    {
        $this->status = 503;
        $user = User::factory()->create();

        $job = (new ReportAccountManagerUser($user->id))->withFakeQueueInteractions();
        $job->handle(app(AccountManagerClient::class));

        $job->assertReleased(60);
        $this->assertSame(3, $job->tries);
    }

    public function test_the_full_sync_sends_all_users_except_trashed_ones(): void
    {
        $users = User::factory()->count(3)->create();
        $users[2]->delete();

        $this->report()->expectsOutputToContain('2 upserted, 1 removed')->assertSuccessful();

        $puts = $this->requests('PUT');
        $this->assertCount(1, $puts);
        $this->assertSame(self::URL, $puts[0]->url());
        $this->assertTrue($puts[0]['complete']);
        $this->assertSame('2026-11-14T10:00:00+00:00', $puts[0]->header('X-Sync-Started-At')[0]);
        $this->assertSame(
            [(string) $users[0]->id, (string) $users[1]->id],
            array_column($puts[0]['users'], 'id'),
        );
    }

    public function test_the_full_sync_marks_only_the_last_chunk_complete(): void
    {
        User::factory()->count(3)->create();

        $users = new AccountManagerUsers;
        $users->chunkSize = 2;
        $result = $users->sync(app(AccountManagerClient::class));

        $puts = $this->requests('PUT');
        $this->assertCount(2, $puts);
        $this->assertCount(2, $puts[0]['users']);
        $this->assertFalse($puts[0]['complete']);
        $this->assertCount(1, $puts[1]['users']);
        $this->assertTrue($puts[1]['complete']);
        $this->assertSame($puts[0]->header('X-Sync-Started-At'), $puts[1]->header('X-Sync-Started-At'));
        $this->assertSame(['upserted' => 3, 'removed' => 2], $result);
    }

    public function test_the_full_sync_without_users_still_completes(): void
    {
        $this->report()->assertSuccessful();

        $puts = $this->requests('PUT');
        $this->assertCount(1, $puts);
        $this->assertSame([], $puts[0]['users']);
        $this->assertTrue($puts[0]['complete']);
    }

    public function test_the_full_sync_fails_while_the_account_manager_is_down(): void
    {
        User::factory()->create();
        $this->status = 503;

        $this->report()->assertFailed();
    }
}
