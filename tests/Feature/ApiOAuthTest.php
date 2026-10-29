<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiOAuthTest extends TestCase
{
    private const REDIRECT_URI = 'https://shoppinglist.example/recipes/callback';

    protected function setUp(): void
    {
        parent::setUp();

        // Throwaway keys, so the tests do not depend on storage/oauth-*.key.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);

        config([
            'passport.private_key' => $privateKey,
            'passport.public_key' => $details['key'],
        ]);
    }

    private function client(): Client
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Shoppinglist',
            [self::REDIRECT_URI],
        );
    }

    /**
     * @return array<string, string>
     */
    private function authorizeQuery(Client $client, string $scope = ''): array
    {
        return array_filter([
            'client_id' => (string) $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => 'state-123',
        ]);
    }

    public function test_api_endpoints_reject_requests_without_a_token(): void
    {
        $recipeId = 1;

        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/foods')->assertUnauthorized();
        $this->postJson('/api/recipes/search', ['food_ids' => [1]])->assertUnauthorized();
        $this->getJson('/api/recipes/'.$recipeId)->assertUnauthorized();
        $this->postJson('/api/recipe-drafts', [])->assertUnauthorized();
        $this->getJson('/api/vocabulary')->assertUnauthorized();
    }

    public function test_api_errors_are_json_even_without_an_accept_header(): void
    {
        $this->get('/api/user')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_a_web_session_does_not_authenticate_the_api(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_an_invalid_bearer_token_is_rejected(): void
    {
        $this->withToken('not-a-token')->getJson('/api/user')->assertUnauthorized();
    }

    public function test_the_user_endpoint_returns_id_name_and_email(): void
    {
        $user = User::factory()->create(['email' => 'cook@example.ch']);
        $user->author->update(['name' => 'Benjamin']);

        Passport::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => 'Benjamin',
                'email' => 'cook@example.ch',
            ]);
    }

    public function test_the_foods_endpoint_lists_all_foods_without_trashed_ones(): void
    {
        $tomato = Food::factory()->create(['name' => 'Tomate']);
        $mince = Food::factory()->create(['name' => 'Hackfleisch']);
        Food::factory()->create(['name' => 'Gelöscht'])->delete();

        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/foods')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $mince->id, 'name' => 'Hackfleisch'],
                ['id' => $tomato->id, 'name' => 'Tomate'],
            ]]);
    }

    public function test_a_guest_is_sent_to_the_filament_login_before_authorizing(): void
    {
        $this->get(route('passport.authorizations.authorize', $this->authorizeQuery($this->client())))
            ->assertRedirect(route('filament.app.auth.login'));
    }

    public function test_an_unverified_user_must_verify_the_email_address_first(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)
            ->get(route('passport.authorizations.authorize', $this->authorizeQuery($this->client())))
            ->assertRedirect(route('filament.app.auth.email-verification.prompt'));
    }

    public function test_the_approval_screen_names_the_client(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('passport.authorizations.authorize', $this->authorizeQuery($this->client())))
            ->assertOk()
            ->assertSee('Shoppinglist')
            ->assertSee(route('passport.authorizations.approve'), false);
    }

    public function test_the_recipes_scope_is_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('passport.authorizations.authorize', $this->authorizeQuery($this->client(), 'recipes')))
            ->assertOk();
    }

    public function test_the_full_authorization_code_flow_issues_a_working_refreshable_token(): void
    {
        $user = User::factory()->create();
        $client = $this->client();

        $this->actingAs($user)
            ->get(route('passport.authorizations.authorize', $this->authorizeQuery($client)))
            ->assertOk();

        $approve = $this->post(route('passport.authorizations.approve'), [
            'auth_token' => session('authToken'),
        ]);

        $location = (string) $approve->headers->get('Location');
        $this->assertStringStartsWith(self::REDIRECT_URI.'?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('state-123', $query['state']);

        $tokens = $this->postJson(route('passport.token'), [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $query['code'],
        ])->assertOk()->json();

        $this->assertSame(3600, $tokens['expires_in']);

        // The API guard must not fall back to the web session.
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->withToken($tokens['access_token'])
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);

        $this->postJson(route('passport.token'), [
            'grant_type' => 'refresh_token',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token', 'expires_in']);
    }
}
