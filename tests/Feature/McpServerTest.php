<?php

namespace Tests\Feature;

use App\Enums\Measure;
use App\Filament\Pages\ConnectedAiClients;
use App\Mail\LookupRequestedMail;
use App\Mcp\McpPermission;
use App\Mcp\Servers\RecipesServer;
use App\Mcp\Tools\AddRecipePhotoTool;
use App\Mcp\Tools\CreateRecipeTool;
use App\Mcp\Tools\DeleteRecipeTool;
use App\Mcp\Tools\FindSimilarTool;
use App\Mcp\Tools\GenerateRecipePhotoTool;
use App\Mcp\Tools\GetRecipeFormatTool;
use App\Mcp\Tools\GetRecipeTool;
use App\Mcp\Tools\SearchRecipesTool;
use App\Mcp\Tools\UpdateRecipeTool;
use App\Models\Category;
use App\Models\Food;
use App\Models\IngredientAttribute;
use App\Models\McpGrant;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipePhotos;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    private const REDIRECT_URI = 'http://localhost:33418/callback';

    /** A 1×1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

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

    public function test_a_duplicate_name_is_numbered_or_reported_without_leaving_requests_behind(): void
    {
        Mail::fake();
        User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Create]);
        Category::factory()->create(['name' => 'Dessert']);
        Unit::factory()->create(['name' => 'g', 'name_shortcut' => 'g']);
        Unit::factory()->create(['name' => 'Prise', 'name_shortcut' => null]);

        foreach (['Mehl', 'Butter', 'Margarine', 'Apfel', 'Salz'] as $food) {
            Food::factory()->create(['name' => $food]);
        }

        $trashed = Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => null, 'author_id' => $user->author_id]);
        $trashed->delete();
        $recipe = [...RecipeJsonSchema::example(), 'cookbook' => null];

        // "error": nothing saved, not even the missing tags, and no mail.
        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create_missing' => true, 'on_duplicate' => 'error'])
            ->assertHasErrors()
            ->assertSee(__('A recipe named ":name" already exists in this cookbook (in the trash).', ['name' => 'Apfelkuchen']));
        $this->assertSame(0, Recipe::query()->count());
        $this->assertDatabaseMissing('tags', ['name' => 'Kuchen']);
        Mail::assertNothingSent();

        // Default "rename": saved under a free name; the trashed recipe stays where it is.
        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create_missing' => true])
            ->assertOk()
            ->assertSee('"name":"Apfelkuchen (2)"')
            ->assertSee('"renamed":true')
            ->assertSee('"original_name":"Apfelkuchen"');
        $this->assertSame('Apfelkuchen (2)', Recipe::query()->sole()->name);
        $this->assertSoftDeleted($trashed);
        Mail::assertSent(LookupRequestedMail::class);

        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'on_duplicate' => 'skip'])->assertHasErrors();
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
        Storage::fake('recipes');
        Storage::disk('recipes')->put($own->id.'/photo.jpg', 'binary');
        RecipesServer::tool(DeleteRecipeTool::class, ['id' => $own->id])->assertOk();

        // Only to the trash: restorable, with its photos.
        $this->assertSoftDeleted($own);
        Storage::disk('recipes')->assertExists($own->id.'/photo.jpg');
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

    /**
     * Everything of the example recipe is known except the top-level ingredients:
     * "Rohzucker" (similar to "Zucker"), "Yuzu" (nothing similar) and "Rüebli" (means "Karotte").
     *
     * @return array<string, mixed>
     */
    private function recipeWithNewFoods(): array
    {
        Category::factory()->create(['name' => 'Dessert']);
        Unit::factory()->create(['name' => 'g', 'name_shortcut' => 'g', 'name_plural' => null, 'name_plural_shortcut' => null, 'measure' => Measure::Mass, 'base_amount' => 1]);
        Unit::factory()->create(['name' => 'Stück', 'name_shortcut' => null, 'name_plural' => null, 'name_plural_shortcut' => null]);

        foreach (['Mehl', 'Butter', 'Margarine', 'Apfel', 'Zucker', 'Karotte'] as $food) {
            Food::factory()->create(['name' => $food]);
        }

        foreach (['gesiebt', 'weich', 'säuerlich', 'geschält'] as $attribute) {
            IngredientAttribute::factory()->create(['name' => $attribute]);
        }

        Tag::factory()->create(['name' => 'Kuchen']);
        Tag::factory()->create(['name' => 'Herbst']);

        $ingredient = fn (string $food, ?string $unit): array => ['amount' => 1, 'amount_max' => null, 'unit' => $unit, 'food' => $food, 'attributes' => [], 'alternatives' => []];

        return [
            ...RecipeJsonSchema::example(),
            'cookbook' => null,
            'ingredients' => [$ingredient('Rohzucker', 'g'), $ingredient('Yuzu', 'Stück'), $ingredient('Rüebli', 'g')],
        ];
    }

    public function test_similar_entries_stop_the_creation_until_the_user_decides(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Create]);
        $recipe = $this->recipeWithNewFoods();

        // create_missing only creates values without suggestions: "Rohzucker" stops everything.
        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create_missing' => true])
            ->assertOk()
            ->assertSee('"saved":false')
            ->assertSee('"value":"Rohzucker","suggestions":["Zucker"]')
            ->assertSee('Ask the user');
        $this->assertSame(0, Recipe::query()->count());
        $this->assertDatabaseMissing('foods', ['name' => 'Yuzu']);
        $this->assertDatabaseMissing('foods', ['name' => 'Rüebli']);

        // A map to an entry that does not exist is reported.
        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create' => ['Rohzucker', 'Yuzu'], 'map' => ['Rüebli' => 'Pastinake']])
            ->assertOk()
            ->assertSee('"saved":false')
            ->assertSee('No existing food named \"Pastinake\" for map.');
        $this->assertSame(0, Recipe::query()->count());

        // The user agreed: "Rohzucker" and "Yuzu" are new, "Rüebli" is "Karotte".
        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create' => ['rohzucker', 'Yuzu'], 'map' => ['Rüebli' => 'Karotten']])
            ->assertOk()
            ->assertSee('"saved":true');

        $created = Recipe::query()->sole();
        $karotte = Food::query()->where('name', 'Karotte')->sole();
        $this->assertSame(1, $created->ingredients()->where('food_id', $karotte->id)->count());
        $this->assertDatabaseMissing('foods', ['name' => 'Rüebli']);
        $this->assertSame($user->id, Food::query()->where('name', 'Rohzucker')->sole()->requested_by);
        $this->assertSame($user->id, Food::query()->where('name', 'Yuzu')->sole()->requested_by);
        Mail::assertSent(LookupRequestedMail::class, fn (LookupRequestedMail $mail): bool => $mail->hasTo($admin->email)
            && $mail->requests === [['type' => 'food', 'value' => 'Rohzucker'], ['type' => 'food', 'value' => 'Yuzu']]);
    }

    public function test_create_missing_still_creates_values_without_suggestions(): void
    {
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Create]);
        $recipe = $this->recipeWithNewFoods();
        $recipe['ingredients'] = [$recipe['ingredients'][1]];

        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create_missing' => true])
            ->assertOk()
            ->assertSee('"saved":true');

        $this->assertSame($user->id, Food::query()->where('name', 'Yuzu')->sole()->requested_by);
    }

    public function test_conversions_are_stored_on_created_foods_only(): void
    {
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Create]);
        $recipe = $this->recipeWithNewFoods();
        $recipe['ingredients'] = [$recipe['ingredients'][1]];
        $piece = Unit::query()->where('name', 'Stück')->sole();
        $grams = Unit::query()->where('name', 'g')->sole();

        // Out of range, not a piece unit, impossible weight: nothing saved.
        foreach ([
            ['Yuzu' => ['density' => 50]],
            ['Yuzu' => ['measure' => 'weight']],
            ['Yuzu' => ['piece_weights' => ['g' => 10]]],
            ['Yuzu' => ['piece_weights' => ['Stück' => 0]]],
            // Months 1–12, each once, as a list.
            ['Yuzu' => ['season_months' => [0, 5]]],
            ['Yuzu' => ['season_months' => [13]]],
            ['Yuzu' => ['storage_months' => [3, 3]]],
            ['Yuzu' => ['storage_months' => ['März']]],
            ['Yuzu' => ['season_months' => 5]],
        ] as $conversions) {
            RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create' => ['Yuzu'], 'conversions' => $conversions])
                ->assertHasErrors();
        }

        $this->assertSame(0, Recipe::query()->count());
        $this->assertDatabaseMissing('foods', ['name' => 'Yuzu']);

        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create' => ['Yuzu'], 'conversions' => [
            'yuzu' => ['measure' => 'mass', 'density' => 0.9, 'piece_weights' => ['Stück' => 80], 'season_months' => [11, 10, 12], 'storage_months' => [1]],
            'Mehl' => ['measure' => 'volume', 'density' => 0.5, 'season_months' => [6]],
        ]])
            ->assertOk()
            ->assertSee('"saved":true')
            ->assertSee('"conversions_ignored":["Mehl"]');

        $yuzu = Food::query()->where('name', 'Yuzu')->sole();
        $this->assertSame($user->id, $yuzu->requested_by);
        $this->assertSame(Measure::Mass, $yuzu->measure);
        $this->assertSame(0.9, $yuzu->density);
        $this->assertSame([10, 11, 12], $yuzu->season_months);
        $this->assertSame([1], $yuzu->storage_months);
        $this->assertSame([$piece->id => 80.0], $yuzu->unitWeights()->pluck('grams', 'unit_id')->all());
        $this->assertNotSame($grams->id, $yuzu->unitWeights()->value('unit_id'));

        // Existing foods are maintained by admins.
        $flour = Food::query()->where('name', 'Mehl')->sole();
        $this->assertNull($flour->measure);
        $this->assertNull($flour->season_months);
    }

    public function test_an_admin_stores_season_data_on_a_created_food(): void
    {
        $admin = User::factory()->admin()->create();
        $this->connect($admin, [McpPermission::Create]);
        $recipe = $this->recipeWithNewFoods();
        $recipe['ingredients'] = [$recipe['ingredients'][1]];

        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create' => ['Yuzu'], 'conversions' => [
            'Yuzu' => ['season_months' => ['11', 12]],
        ]])
            ->assertOk()
            ->assertSee('"saved":true');

        $yuzu = Food::query()->where('name', 'Yuzu')->sole();
        $this->assertNull($yuzu->requested_at);
        $this->assertSame([11, 12], $yuzu->season_months);
        $this->assertNull($yuzu->storage_months);
        $this->assertNull($yuzu->measure);
    }

    public function test_the_format_and_tool_description_explain_season_data(): void
    {
        $this->connect(User::factory()->create(), [McpPermission::Create]);

        RecipesServer::tool(GetRecipeFormatTool::class)
            ->assertOk()
            ->assertSee('season_months')
            ->assertSee('storage_months');

        /** @var list<array<string, mixed>> $tools */
        $tools = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['MCP-Protocol-Version' => '2025-11-25'])
            ->assertOk()
            ->json('result.tools');
        $tool = collect($tools)->firstWhere('name', 'create-recipe');
        $description = (string) data_get($tool, 'inputSchema.properties.conversions.description');

        $this->assertStringContainsString('"season_months": [5, 6, 7]', $description);
        $this->assertStringContainsString('storage_months', $description);
    }

    public function test_find_similar_lists_exact_and_similar_entries_the_user_may_see(): void
    {
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Read]);
        $this->recipeWithNewFoods();
        Food::query()->forceCreate(['name' => 'Yuzu', 'requested_by' => User::factory()->create()->id, 'requested_at' => now()]);

        $this->assertContains('find-similar', $this->listedTools());

        RecipesServer::tool(FindSimilarTool::class, ['type' => 'food', 'names' => ['Karotten', 'Rohzucker', 'Yuzu']])
            ->assertOk()
            ->assertSee('"name":"Karotten","status":"exact","match":{"id":'.Food::query()->where('name', 'Karotte')->value('id').',"name":"Karotte"}')
            ->assertSee('"name":"Rohzucker","status":"similar","similar":[{"id":'.Food::query()->where('name', 'Zucker')->value('id').',"name":"Zucker"')
            // Another user's pending request is not offered.
            ->assertSee('"name":"Yuzu","status":"none","similar":[]');

        RecipesServer::tool(FindSimilarTool::class, ['type' => 'unit', 'names' => ['Stk', 'Stück']])
            ->assertOk()
            ->assertSee('"name":"Stück","status":"exact"');

        RecipesServer::tool(FindSimilarTool::class, ['type' => 'author', 'names' => ['Anna']])->assertHasErrors();
        RecipesServer::tool(FindSimilarTool::class, ['type' => 'food', 'names' => []])->assertHasErrors();
    }

    public function test_photos_are_added_from_a_public_url_or_base64(): void
    {
        Storage::fake('recipes');
        $this->fakeDns();
        Http::fake(['bilder.test/roesti.png' => Http::response((string) base64_decode(self::PNG))]);
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Read, McpPermission::Update]);
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null, 'name' => 'Rösti', 'photos' => [
            ['path' => 'alt.jpg', 'source' => null, 'is_ai_generated' => false],
        ]]);

        $this->assertContains('add-recipe-photo', $this->listedTools());

        RecipesServer::tool(AddRecipePhotoTool::class, ['recipe_id' => $recipe->id, 'url' => 'https://bilder.test/roesti.png', 'source' => 'bilder.test'])
            ->assertOk()
            ->assertSee('"saved":true')
            ->assertSee('"photos":2');

        RecipesServer::tool(AddRecipePhotoTool::class, ['recipe_id' => $recipe->id, 'data' => 'data:image/png;base64,'.self::PNG, 'is_ai_generated' => true])
            ->assertOk()
            ->assertSee('"photos":3');

        $photos = $recipe->fresh()->photos->values();
        $this->assertSame('alt.jpg', $photos[0]->name());
        $this->assertSame('bilder.test', $photos[1]->source());
        $this->assertFalse($photos[1]->isAiGenerated());
        $this->assertTrue($photos[2]->isAiGenerated());
        $this->assertStringEndsWith('.png', $photos[2]->name());
        Storage::disk('recipes')->assertExists($recipe->id.'/'.$photos[1]->name());
        Storage::disk('recipes')->assertExists($recipe->id.'/'.$photos[2]->name());
    }

    public function test_invalid_too_large_or_internal_photos_are_refused(): void
    {
        Storage::fake('recipes');
        $this->fakeDns(['intern.test' => ['192.168.1.10']]);
        Http::fake([
            'bilder.test/text.png' => Http::response('<html>no image</html>'),
            'bilder.test/gross.png' => Http::response(str_repeat('a', RecipePhotos::MAX_DOWNLOAD_BYTES + 1)),
            'bilder.test/umleitung' => Http::response('', 302, ['Location' => 'http://intern.test/bild.png']),
        ]);
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Update]);
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null, 'photos' => []]);
        $add = fn (array $arguments) => RecipesServer::tool(AddRecipePhotoTool::class, ['recipe_id' => $recipe->id, ...$arguments]);

        // Content decides, not the declared type.
        $add(['data' => 'data:image/png;base64,'.base64_encode('<?php echo 1;')])->assertHasErrors(['not a supported image']);
        $add(['data' => 'data:text/plain;base64,'.base64_encode('text')])->assertHasErrors(['not a supported image']);
        $add(['url' => 'https://bilder.test/text.png'])->assertHasErrors(['not a supported image']);
        $add(['data' => 'data:image/png;base64,'.str_repeat('A', (int) ceil(RecipeJsonSchema::MAX_PHOTO_BYTES / 3 * 4) + 200)])->assertHasErrors(['too large']);
        $add(['url' => 'https://bilder.test/gross.png'])->assertHasErrors(['too large']);
        $add(['url' => 'http://127.0.0.1/bild.png'])->assertHasErrors(['Only public web addresses']);
        $add(['url' => 'https://bilder.test/umleitung'])->assertHasErrors(['Only public web addresses']);
        $add(['url' => 'https://bilder.test/text.png', 'data' => 'data:image/png;base64,'.self::PNG])->assertHasErrors();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'intern.test') || str_contains($request->url(), '127.0.0.1'));
        $this->assertCount(0, $recipe->fresh()->photos);
    }

    public function test_adding_photos_needs_the_permission_and_the_user_s_rights(): void
    {
        Storage::fake('recipes');
        $user = User::factory()->create();
        $foreign = Recipe::factory()->create(['is_public' => true, 'cookbook_id' => null, 'photos' => []]);
        $own = Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null, 'photos' => []]);
        $png = 'data:image/png;base64,'.self::PNG;

        $this->connect($user, [McpPermission::Update]);
        RecipesServer::tool(AddRecipePhotoTool::class, ['recipe_id' => $foreign->id, 'data' => $png])->assertHasErrors(['No recipe with this id that you may edit.']);

        McpGrant::query()->where('user_id', $user->id)->update(['permissions' => ['read', 'create']]);
        $this->assertNotContains('add-recipe-photo', $this->listedTools());
        RecipesServer::tool(AddRecipePhotoTool::class, ['recipe_id' => $own->id, 'data' => $png])->assertHasErrors();

        $this->assertCount(0, $own->fresh()->photos);
        $this->assertCount(0, $foreign->fresh()->photos);
    }

    public function test_the_app_s_ai_generates_a_photo_within_the_limit(): void
    {
        Storage::fake('recipes');
        config(['services.openai.key' => 'test-key', 'services.openai.url' => 'https://openai.test/v1', 'services.openai.hourly_limit' => 1]);
        Http::fake(['openai.test/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('jpeg-bytes')]]])]);
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Update]);
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null, 'name' => 'Rösti', 'photos' => []]);

        $this->assertContains('generate-recipe-photo', $this->listedTools());

        RecipesServer::tool(GenerateRecipePhotoTool::class, ['recipe_id' => $recipe->id])
            ->assertOk()
            ->assertSee('"saved":true')
            ->assertSee('"is_ai_generated":true');

        $photo = $recipe->fresh()->photos->sole();
        $this->assertTrue($photo->isAiGenerated());
        $this->assertSame('ChatGPT (OpenAI)', $photo->source());
        Storage::disk('recipes')->assertExists($recipe->id.'/'.$photo->name());
        $this->assertDatabaseHas('ai_usages', ['user_id' => $user->id, 'feature' => 'image', 'images' => 1]);

        RecipesServer::tool(GenerateRecipePhotoTool::class, ['recipe_id' => $recipe->id])->assertHasErrors(['Too many AI requests']);
        Http::assertSentCount(1);
    }

    public function test_generating_photos_needs_ai_access(): void
    {
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Update]);
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null, 'photos' => []]);

        // Without an OpenAI key the tool is not offered.
        $this->assertNotContains('generate-recipe-photo', $this->listedTools());
        RecipesServer::tool(GenerateRecipePhotoTool::class, ['recipe_id' => $recipe->id])->assertHasErrors();

        config(['services.openai.key' => 'test-key', 'services.openai.url' => 'https://openai.test/v1']);
        $user->update(['ai_enabled' => false]);
        $this->assertNotContains('generate-recipe-photo', $this->listedTools());
        RecipesServer::tool(GenerateRecipePhotoTool::class, ['recipe_id' => $recipe->id])->assertHasErrors();

        Http::assertNothingSent();
        $this->assertCount(0, $recipe->fresh()->photos);
    }

    public function test_created_and_updated_recipes_use_swiss_spelling(): void
    {
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Create, McpPermission::Update]);
        Category::factory()->create(['name' => 'Dessert']);
        Tag::factory()->create(['name' => 'Süsses']);
        $recipe = [
            'name' => 'Süßkartoffel-Kuchen',
            'category' => 'Dessert',
            'complexity' => RecipeJsonSchema::complexities()[0],
            'instructions' => '1. Große Süßkartoffeln reiben.',
            'tags' => ['Süßes'],
            'ingredients' => [['amount' => 2, 'unit' => null, 'food' => 'Süßkartoffel', 'attributes' => ['groß']]],
        ];

        RecipesServer::tool(CreateRecipeTool::class, ['recipe' => $recipe, 'create' => ['Süßkartoffel', 'groß']])
            ->assertOk()
            ->assertSee('"saved":true');

        $created = Recipe::query()->sole();
        $this->assertSame('Süsskartoffel-Kuchen', $created->name);
        $this->assertStringContainsString('Grosse Süsskartoffeln', (string) $created->instructions);
        $this->assertTrue(Food::query()->where('name', 'Süsskartoffel')->exists());
        $this->assertTrue(IngredientAttribute::query()->where('name', 'gross')->exists());
        $this->assertSame(['Süsses'], $created->tags()->pluck('name')->all());

        RecipesServer::tool(UpdateRecipeTool::class, ['id' => $created->id, 'name' => 'Süßkartoffel-Torte', 'serving_type' => 'Stück', 'instructions' => '1. Weißen Zucker beigeben.', 'tags' => ['Süßes']])
            ->assertOk();

        $created->refresh();
        $this->assertSame('Süsskartoffel-Torte', $created->name);
        $this->assertStringContainsString('Weissen Zucker', (string) $created->instructions);

        // The instructions tell AI clients, too.
        $initialize = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
            'protocolVersion' => '2025-11-25', 'capabilities' => [], 'clientInfo' => ['name' => 'Test', 'version' => '1.0'],
        ]])->assertOk();
        $this->assertStringContainsString('"ss" instead of "ß"', (string) $initialize->json('result.instructions'));
        $this->assertStringContainsString('find-similar', (string) $initialize->json('result.instructions'));
        $this->assertStringContainsString('"ss" instead of "ß"', app(CreateRecipeTool::class)->description());
    }

    public function test_spelling_stays_as_sent_when_disabled(): void
    {
        config(['app.recipe_spelling' => 'de_DE']);
        $user = User::factory()->create();
        $this->connect($user, [McpPermission::Update]);
        $own = Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null]);

        RecipesServer::tool(UpdateRecipeTool::class, ['id' => $own->id, 'name' => 'Weißwurst'])->assertOk();

        $this->assertSame('Weißwurst', $own->fresh()->name);
        $this->assertStringNotContainsString('instead of "ß"', app(UpdateRecipeTool::class)->description());
    }
}
