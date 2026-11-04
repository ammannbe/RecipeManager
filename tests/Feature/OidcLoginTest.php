<?php

namespace Tests\Feature;

use App\Auth\OidcClaims;
use App\Auth\OidcLabel;
use App\Filament\Auth\Login;
use App\Models\Author;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use SocialiteProviders\OIDC\InvalidTokenException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OidcLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'login.oidc.enabled' => true,
            'login.oidc.label' => 'Pocket ID',
            'login.oidc.registration' => true,
            'login.oidc.required_group' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function identity(array $claims = []): SocialiteUser
    {
        $claims = [
            'sub' => 'sub-123',
            'email' => 'cook@example.com',
            'email_verified' => true,
            'name' => 'Pocket Cook',
            ...$claims,
        ];

        return (new SocialiteUser)->setRaw($claims)->map([
            'id' => $claims['sub'],
            'email' => $claims['email'],
            'name' => $claims['name'],
            'nickname' => null,
        ]);
    }

    private function provider(): Mockery\MockInterface
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('scopes')->andReturnSelf();

        Socialite::shouldReceive('driver')->with('oidc')->andReturn($provider);

        return $provider;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return TestResponse<Response>
     */
    private function signIn(array $claims = []): TestResponse
    {
        $this->provider()->shouldReceive('user')->andReturn($this->identity($claims));

        return $this->get(route('oidc.callback'));
    }

    public function test_the_routes_are_hidden_while_oidc_is_disabled(): void
    {
        config(['login.oidc.enabled' => false]);

        $this->get(route('oidc.redirect'))->assertNotFound();
        $this->get(route('oidc.callback'))->assertNotFound();
    }

    public function test_the_redirect_goes_to_the_provider(): void
    {
        $this->provider()->shouldReceive('redirect')->andReturn(new RedirectResponse('https://login.example.com/authorize'));

        $this->get(route('oidc.redirect'))->assertRedirect('https://login.example.com/authorize');
    }

    public function test_a_linked_user_is_signed_in(): void
    {
        $user = User::factory()->create(['oidc_sub' => 'sub-123', 'email' => 'other@example.com']);

        $this->signIn()->assertRedirect(Filament::getPanel('app')->getUrl())
            // Stays signed in beyond the session lifetime.
            ->assertCookie(Auth::guard()->getRecallerName());

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_verified_local_account_is_linked_by_email(): void
    {
        $user = User::factory()->create(['email' => 'Cook@Example.com']);
        $password = $user->password;

        $this->signIn();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('sub-123', $user->fresh()->oidc_sub);
        $this->assertSame($password, $user->fresh()->password);
    }

    public function test_an_unverified_local_account_loses_its_password_when_linked(): void
    {
        $user = User::factory()->create(['email' => 'cook@example.com', 'email_verified_at' => null]);
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'foreign-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

        $this->signIn();

        $this->assertDatabaseMissing('sessions', ['id' => 'foreign-session']);

        $user->refresh();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_an_unverified_provider_email_is_not_linked(): void
    {
        $user = User::factory()->create(['email' => 'cook@example.com']);

        $this->signIn(['email_verified' => false])->assertRedirect(Filament::getPanel('app')->getLoginUrl());

        $this->assertGuest();
        $this->assertNull($user->fresh()->oidc_sub);
    }

    public function test_an_account_linked_to_another_sign_in_is_not_taken_over(): void
    {
        $user = User::factory()->create(['email' => 'cook@example.com', 'oidc_sub' => 'someone-else']);

        $this->signIn();

        $this->assertGuest();
        $this->assertSame('someone-else', $user->fresh()->oidc_sub);
    }

    public function test_an_unknown_user_gets_an_account_with_a_unique_author_name(): void
    {
        Author::factory()->create(['name' => 'Pocket Cook']);

        $this->signIn();

        $user = User::query()->where('oidc_sub', 'sub-123')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('cook@example.com', $user->email);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('Pocket Cook 2', $user->author->name);
    }

    public function test_no_account_is_created_when_oidc_registration_is_disabled(): void
    {
        config(['login.oidc.registration' => false]);

        $this->signIn();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'cook@example.com']);
    }

    public function test_the_required_group_is_enforced(): void
    {
        config(['login.oidc.required_group' => 'svc-recipes']);
        User::factory()->create(['oidc_sub' => 'sub-123']);

        $this->provider()->shouldReceive('user')->andReturn(
            $this->identity(['groups' => ['svc-other']]),
            $this->identity(['groups' => ['svc-recipes']]),
        );

        $this->get(route('oidc.callback'));
        $this->assertGuest();

        $this->get(route('oidc.callback'));
        $this->assertAuthenticated();
    }

    public function test_a_deleted_user_cannot_sign_in(): void
    {
        $user = User::factory()->create(['oidc_sub' => 'sub-123']);
        $user->delete();

        $this->signIn();

        $this->assertGuest();
    }

    public function test_provider_errors_do_not_sign_in(): void
    {
        $this->provider()->shouldReceive('user')->andThrow(new RuntimeException('Invalid state'));

        $this->get(route('oidc.callback'))->assertRedirect(Filament::getPanel('app')->getLoginUrl());

        $this->assertGuest();
    }

    public function test_a_signed_in_user_can_link_the_sign_in_from_the_profile(): void
    {
        $user = User::factory()->create(['email' => 'different@example.com']);
        $provider = $this->provider();
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://login.example.com/authorize'));
        $provider->shouldReceive('user')->andReturn($this->identity());

        $this->actingAs($user)->get(route('oidc.redirect', ['link' => 1]));
        $this->get(route('oidc.callback'))->assertRedirect();

        $this->assertSame('sub-123', $user->fresh()->oidc_sub);
        $this->assertDatabaseMissing('users', ['email' => 'cook@example.com']);
    }

    public function test_a_sign_in_linked_elsewhere_cannot_be_linked_again(): void
    {
        User::factory()->create(['oidc_sub' => 'sub-123']);
        $user = User::factory()->create();
        $provider = $this->provider();
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://login.example.com/authorize'));
        $provider->shouldReceive('user')->andReturn($this->identity());

        $this->actingAs($user)->get(route('oidc.redirect', ['link' => 1]));
        $this->get(route('oidc.callback'));

        $this->assertNull($user->fresh()->oidc_sub);
    }

    public function test_the_id_token_claims_are_checked(): void
    {
        $valid = (object) ['iss' => 'https://login.example.com', 'aud' => 'client', 'exp' => now()->addMinute()->getTimestamp()];

        OidcClaims::assertValid($valid, 'https://login.example.com/', 'client');

        foreach ([
            ['iss' => 'https://evil.example.com'],
            ['aud' => 'other-client'],
            ['aud' => ['client', 'other-client']],
            ['exp' => now()->subMinute()->getTimestamp()],
        ] as $override) {
            try {
                OidcClaims::assertValid((object) [...(array) $valid, ...$override], 'https://login.example.com', 'client');
                $this->fail('Invalid claims were accepted: '.json_encode($override));
            } catch (InvalidTokenException) {
                $this->addToAssertionCount(1);
            }
        }

        OidcClaims::assertValid((object) [...(array) $valid, 'aud' => ['client', 'other'], 'azp' => 'client'], 'https://login.example.com', 'client');
    }

    public function test_without_local_login_only_admins_can_sign_in_with_a_password(): void
    {
        config(['login.local.enabled' => false]);
        Filament::setCurrentPanel('app');

        $user = User::factory()->create(['password' => Hash::make('secret-password')]);
        $admin = User::factory()->admin()->create(['password' => Hash::make('secret-password')]);

        Livewire::withQueryParams(['local' => 1])->test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $this->assertGuest();

        Livewire::withQueryParams(['local' => 1])->test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_without_local_login_the_password_form_is_hidden(): void
    {
        config(['login.local.enabled' => false]);
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)->assertFormFieldDoesNotExist('email');
        Livewire::withQueryParams(['local' => 1])->test(Login::class)->assertFormFieldExists('email');
    }

    public function test_a_user_without_password_can_use_the_backend(): void
    {
        $this->signIn();

        $this->get(Filament::getPanel('app')->getUrl())->assertOk();
        $this->get(Filament::getPanel('app')->getUrl())->assertOk();
    }

    public function test_the_login_page_shows_the_sign_in_button(): void
    {
        $this->get(Filament::getPanel('app')->getLoginUrl())
            ->assertOk()
            ->assertSee('Pocket ID')
            // Above the password form.
            ->assertSeeInOrder([route('oidc.redirect'), 'type="password"'], false);
    }

    public function test_the_register_page_offers_the_provider_and_its_signup(): void
    {
        config(['login.oidc.signup_url' => 'https://konto.example.com/register']);

        $this->get(Filament::getPanel('app')->getRegistrationUrl())
            ->assertOk()
            ->assertSee('Register with Pocket ID')
            ->assertSee('https://konto.example.com/register', false);

        config(['login.oidc.registration' => false]);

        $this->get(Filament::getPanel('app')->getRegistrationUrl())
            ->assertOk()
            ->assertDontSee(route('oidc.redirect'), false);
    }

    public function test_the_label_falls_back_to_the_name_published_by_pocket_id(): void
    {
        config(['login.oidc.label' => null, 'services.oidc.base_url' => 'https://login.example.com']);
        Http::fake(['login.example.com/api/application-configuration' => Http::response([
            ['key' => 'appName', 'type' => '', 'value' => 'NarrenLogin'],
        ])]);

        $this->assertSame('NarrenLogin', OidcLabel::get());
        $this->get(Filament::getPanel('app')->getLoginUrl())->assertSee('NarrenLogin');
        Http::assertSentCount(1);
    }

    public function test_the_label_is_sso_when_the_provider_publishes_no_name(): void
    {
        config(['login.oidc.label' => null, 'services.oidc.base_url' => 'https://login.example.com']);
        Http::fake(['*' => Http::response('Not found', 404)]);

        $this->assertSame(OidcLabel::FALLBACK, OidcLabel::get());
    }
}
