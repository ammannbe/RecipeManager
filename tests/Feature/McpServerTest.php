<?php

namespace Tests\Feature;

use App\Filament\Pages\ConnectedAiClients;
use App\Mcp\McpPermission;
use App\Mcp\Servers\RecipesServer;
use App\Mcp\Tools\CreateRecipeTool;
use App\Mcp\Tools\DeleteRecipeTool;
use App\Mcp\Tools\GetRecipeTool;
use App\Mcp\Tools\SearchRecipesTool;
use App\Mcp\Tools\UpdateRecipeTool;
use App\Models\Category;
use App\Models\Food;
use App\Models\McpGrant;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\RecipeJsonSchema;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    private const REDIRECT_URI = 'http://localhost:33418/callback';

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);

        config(['passport.private_key' => $privateKey, 'passport.public_key' => $details['key']]);
    }

    private function aiClient(): Client
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient('Claude Code', [self::REDIRECT_URI], confidential: false);
    }

    /**
     * @param  list<McpPermission>  $permissions
     */
    private function connect(User $user, array $permissions): Client
    {
        $client = $this->aiClient();
        McpGrant::query()->create([
            'user_id' => $user->id,
            'oauth_client_id' => $client->getKey(),
            'permissions' => array_map(fn (McpPermission $permission): string => $permission->value, $permissions),
        ]);
        Passport::actingAs($user, [Registrar::OAUTH_SCOPE], 'api', $client);

        return $client;
    }

    /**
     * @return list<string>
     */
    private function listedTools(): array
    {
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['MCP-Protocol-Version' => '2025-11-25'])->assertOk();

        return array_column($response->json('result.tools') ?? [], 'name');
    }

    public function test_clients_register_themselves_and_discover_the_server(): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'Codex', 'redirect_uris' => [self::REDIRECT_URI]])
            ->assertCreated()
            ->assertJsonPath('scope', Registrar::OAUTH_SCOPE);

        $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()->assertJsonFragment(['resource' => url('/mcp')]);
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    }

    public function test_the_approval_screen_stores_the_chosen_permissions_and_issues_a_code(): void
    {
        $user = User::factory()->create();
        $client = $this->aiClient();
        $verifier = str_repeat('v', 64);

        $this->actingAs($user)->get(route('passport.authorizations.authorize', [
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => Registrar::OAUTH_SCOPE,
            'state' => 'xyz',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]))
            ->assertOk()
            ->assertSee('Change recipes')
            ->assertSee('localhost')
            ->assertSee(route('mcp.authorize.approve'), false);

        $response = $this->post(route('mcp.authorize.approve'), ['auth_token' => session('authToken'), 'permissions' => ['read', 'update']]);

        $this->assertStringStartsWith(self::REDIRECT_URI.'?code=', (string) $response->headers->get('Location'));
        $this->assertSame(['read', 'update'], McpGrant::query()->where('user_id', $user->id)->sole()->permissions);
    }

    public function test_only_permitted_tools_are_listed(): void
    {
        $this->connect(User::factory()->create(), [McpPermission::Read]);

        $tools = $this->listedTools();

        $this->assertContains('search-recipes', $tools);
        $this->assertContains('get-recipe', $tools);
        $this->assertNotContains('create-recipe', $tools);
        $this->assertNotContains('delete-recipe', $tools);
    }

    public function test_tokens_without_a_grant_and_companion_app_tokens_are_refused(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, [Registrar::OAUTH_SCOPE], 'api', $this->aiClient());
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();

        Passport::actingAs($user, ['recipes'], 'api', $this->aiClient());
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
    }

    public function test_ai_client_tokens_do_not_work_on_the_json_api(): void
    {
        $this->connect(User::factory()->create(), McpPermission::cases());

        $this->getJson('/api/user')->assertForbidden();
    }

    public function test_reading_respects_the_visibility(): void
    {
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Read]);
        $public = Recipe::factory()->create(['name' => 'Rösti', 'is_public' => true, 'cookbook_id' => null]);
        $private = Recipe::factory()->create(['name' => 'Rösti geheim', 'is_public' => false, 'cookbook_id' => null]);

        RecipesServer::tool(SearchRecipesTool::class, ['query' => 'Rösti'])
            ->assertOk()
            ->assertSee('"id":'.$public->id)
            ->assertDontSee('Rösti geheim');

        RecipesServer::tool(GetRecipeTool::class, ['id' => $public->id])->assertOk()->assertSee('Rösti');
        RecipesServer::tool(GetRecipeTool::class, ['id' => $private->id])->assertHasErrors();
    }

    public function test_tools_without_permission_cannot_be_called(): void
    {
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Read]);
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null]);

        RecipesServer::tool(DeleteRecipeTool::class, ['id' => $recipe->id])->assertHasErrors();

        $this->assertNotSoftDeleted($recipe);
    }

    public function test_creating_matches_known_values_or_hands_over_to_the_wizard(): void
    {
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Create]);
        Category::factory()->create(['name' => 'Dessert']);
        Unit::factory()->create(['name' => 'g', 'name_shortcut' => 'g']);
        Unit::factory()->create(['name' => 'Prise', 'name_shortcut' => null]);

        foreach (['Mehl', 'Butter', 'Margarine', 'Apfel', 'Salz'] as $food) {
            Food::factory()->create(['name' => $food]);
        }

        $recipe = [...RecipeJsonSchema::example(), 'cookbook' => null];

        // Unknown attributes and tags: nothing saved, the wizard takes over.
        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe])
            ->assertOk()
            ->assertSee('"saved":false')
            ->assertSee('import-recipe?draft=');
        $this->assertSame(0, Recipe::query()->count());
        $this->assertSame(1, RecipeDraft::query()->count());

        // With create_missing they become requests of this user.
        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create_missing' => true, 'is_public' => false])
            ->assertOk()
            ->assertSee('"saved":true');

        $created = Recipe::query()->sole();
        $this->assertSame('Apfelkuchen', $created->name);
        $this->assertSame($user->author_id, $created->author_id);
        $this->assertFalse($created->is_public);
        $this->assertNotNull(Tag::query()->where('name', 'Kuchen')->value('requested_at'));
    }

    public function test_updating_and_deleting_need_the_user_s_own_rights(): void
    {
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Update, McpPermission::Delete]);
        $own = Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null, 'servings' => 2]);
        $foreign = Recipe::factory()->create(['is_public' => true, 'cookbook_id' => null]);

        RecipesServer::tool(UpdateRecipeTool::class, ['id' => $own->id, 'servings' => 4, 'instructions' => '1. <b>Rühren</b>'])
            ->assertOk()
            ->assertSee('"saved":true');
        $this->assertSame(4.0, $own->fresh()->servings);
        $this->assertStringContainsString('&lt;b&gt;', (string) $own->fresh()->instructions);

        RecipesServer::tool(UpdateRecipeTool::class, ['id' => $foreign->id, 'name' => 'Meins'])->assertHasErrors();
        RecipesServer::tool(DeleteRecipeTool::class, ['id' => $foreign->id])->assertHasErrors();
        RecipesServer::tool(DeleteRecipeTool::class, ['id' => $own->id])->assertOk();

        $this->assertSoftDeleted($own);
        $this->assertNotSoftDeleted($foreign);
    }

    public function test_users_change_and_revoke_connected_clients(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $client = $this->aiClient();
        $grant = McpGrant::query()->create(['user_id' => $user->id, 'oauth_client_id' => $client->getKey(), 'permissions' => ['read']]);
        $foreign = McpGrant::query()->create(['user_id' => $other->id, 'oauth_client_id' => $client->getKey(), 'permissions' => ['read']]);
        $token = $user->tokens()->create(['id' => str_repeat('a', 80), 'client_id' => $client->getKey(), 'scopes' => [Registrar::OAUTH_SCOPE], 'revoked' => false, 'expires_at' => now()->addHour()]);

        $this->actingAs($user);

        Livewire::test(ConnectedAiClients::class)
            ->assertSee('claude mcp add --transport http')
            ->assertCanSeeTableRecords([$grant])
            ->assertCanNotSeeTableRecords([$foreign])
            ->callTableAction('permissions', $grant, ['permissions' => ['read', 'create']])
            ->assertHasNoTableActionErrors();

        $this->assertSame(['read', 'create'], $grant->fresh()?->permissions);

        Livewire::test(ConnectedAiClients::class)->callTableAction('revoke', $grant);

        $this->assertNull($grant->fresh());
        $this->assertTrue((bool) $token->fresh()?->revoked);
        $this->assertNotNull($foreign->fresh());
    }
}
