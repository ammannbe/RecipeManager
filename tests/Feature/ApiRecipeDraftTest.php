<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Food;
use App\Models\RecipeDraft;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\RecipeJsonSchema;
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
