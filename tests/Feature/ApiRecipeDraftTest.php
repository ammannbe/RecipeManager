<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\RecipeJsonSchema;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiRecipeDraftTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Kürbis-Risotto',
            'category' => 'Rezeptideen',
            'complexity' => 'normal',
            'servings' => 2,
            'instructions' => "1. Kürbis würfeln\n2. Risotto kochen",
            'ingredients' => [
                ['amount' => 300, 'unit' => 'g', 'food' => 'Butternusskürbis'],
                ['amount' => 2, 'unit' => 'Stk.', 'food' => 'Zwiebel'],
            ],
            ...$overrides,
        ];
    }

    public function test_a_draft_is_stored_without_creating_anything(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user);
        $counts = [Food::query()->count(), Unit::query()->count(), Category::query()->count()];

        $response = $this->postJson('/api/recipe-drafts', $this->payload())->assertCreated();

        $draft = RecipeDraft::query()->sole();
        $this->assertSame($user->id, $draft->user_id);
        $this->assertSame('Kürbis-Risotto', $draft->data['name']);
        $this->assertTrue($draft->expires_at->isFuture());
        $response->assertJsonPath('data.url', url('/admin/import-recipe?draft='.$draft->id));

        $this->assertSame($counts, [Food::query()->count(), Unit::query()->count(), Category::query()->count()]);
    }

    public function test_the_import_format_is_validated(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->postJson('/api/recipe-drafts', $this->payload(['complexity' => 'easy', 'name' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipe');

        $this->postJson('/api/recipe-drafts', $this->payload(['photos' => [['url' => 'https://example.com/a.jpg']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photos');

        $this->assertSame(0, RecipeDraft::query()->count());
    }

    public function test_expired_drafts_are_removed(): void
    {
        $old = RecipeDraft::query()->create(['user_id' => User::factory()->create()->id, 'data' => [], 'expires_at' => now()->subMinute()]);
        Passport::actingAs(User::factory()->create());

        $this->postJson('/api/recipe-drafts', $this->payload())->assertCreated();

        $this->assertModelMissing($old);
    }

    public function test_imported_drafts_are_kept_until_their_time_is_up(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $imported = RecipeDraft::query()->create(['user_id' => $user->id, 'data' => [], 'expires_at' => now()->subDay(), 'recipe_id' => $recipe->id, 'imported_at' => now()->subDays(2)]);
        $old = RecipeDraft::query()->create(['user_id' => $user->id, 'data' => [], 'expires_at' => now()->subDays(40), 'recipe_id' => $recipe->id, 'imported_at' => now()->subDays(RecipeDraft::KEEP_IMPORTED_DAYS + 1)]);
        $pending = RecipeDraft::query()->create(['user_id' => $user->id, 'data' => [], 'expires_at' => now()->addHour()]);
        Passport::actingAs($user);

        $this->postJson('/api/recipe-drafts', $this->payload())->assertCreated();

        $this->assertModelExists($imported);
        $this->assertModelExists($pending);
        $this->assertModelMissing($old);

        $command = $this->artisan('recipe-drafts:prune');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();
        $this->assertModelExists($imported);
    }

    public function test_the_state_of_a_pending_or_expired_draft_is_returned(): void
    {
        $user = User::factory()->create();
        $draft = RecipeDraft::query()->create(['user_id' => $user->id, 'data' => [], 'expires_at' => now()->addHour()]);
        Passport::actingAs($user);

        $this->getJson('/api/recipe-drafts/'.$draft->id)
            ->assertOk()
            ->assertJsonPath('data.id', $draft->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.imported_at', null)
            ->assertJsonPath('data.recipe', null);

        $draft->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/recipe-drafts/'.$draft->id)->assertOk()->assertJsonPath('data.status', 'expired');
    }

    public function test_an_imported_draft_returns_its_recipe(): void
    {
        $user = User::factory()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id, 'name' => 'Kürbis-Risotto', 'cookbook_id' => null, 'is_public' => false]);
        $draft = RecipeDraft::query()->create(['user_id' => $user->id, 'data' => [], 'expires_at' => now()->subHour()]);
        RecipeDraft::markImported($draft->id, $recipe);
        Passport::actingAs($user);

        $this->getJson('/api/recipe-drafts/'.$draft->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.recipe', ['id' => $recipe->id, 'name' => 'Kürbis-Risotto', 'url' => route('recipes.show', $recipe)]);

        // Deleted afterwards: still imported, without recipe.
        $recipe->delete();
        $this->getJson('/api/recipe-drafts/'.$draft->id)->assertOk()->assertJsonPath('data.status', 'imported')->assertJsonPath('data.recipe', null);

        $recipe->forceDelete();
        $this->assertNull($draft->refresh()->recipe_id);
        $this->getJson('/api/recipe-drafts/'.$draft->id)->assertOk()->assertJsonPath('data.status', 'imported')->assertJsonPath('data.recipe', null);
    }

    public function test_only_the_owner_sees_a_draft(): void
    {
        $draft = RecipeDraft::query()->create(['user_id' => User::factory()->create()->id, 'data' => [], 'expires_at' => now()->addHour()]);

        $this->getJson('/api/recipe-drafts/'.$draft->id)->assertUnauthorized();

        Passport::actingAs(User::factory()->create());
        $this->getJson('/api/recipe-drafts/'.$draft->id)->assertNotFound();
        $this->getJson('/api/recipe-drafts/'.Str::uuid())->assertNotFound();
        $this->getJson('/api/recipe-drafts/not-a-uuid')->assertNotFound();
    }

    public function test_guests_cannot_store_drafts(): void
    {
        $this->postJson('/api/recipe-drafts', $this->payload())->assertUnauthorized();
    }

    public function test_recipes_can_no_longer_be_created_directly(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->postJson('/api/recipes', $this->payload())->assertNotFound();
    }

    public function test_the_vocabulary_lists_categories_units_and_complexities(): void
    {
        Category::factory()->create(['name' => 'Suppen']);
        Unit::factory()->create(['name' => 'Gramm', 'name_shortcut' => 'g']);
        Unit::factory()->create(['name' => 'Prise', 'name_shortcut' => null]);
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/vocabulary')
            ->assertOk()
            ->assertJsonPath('data.categories', ['Suppen'])
            ->assertJsonPath('data.units', ['g', 'Prise'])
            ->assertJsonPath('data.complexities', RecipeJsonSchema::complexities());
    }
}
