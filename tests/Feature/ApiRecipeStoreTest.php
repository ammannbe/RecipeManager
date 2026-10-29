<?php

namespace Tests\Feature;

use App\Enums\Complexity;
use App\Models\Category;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiRecipeStoreTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Pasta mit Tomaten',
            'servings' => 4,
            'instructions' => "1. Wasser kochen\n2. Pasta kochen",
            'ingredients' => [
                ['amount' => 400, 'unit' => 'g', 'food' => 'Spaghetti'],
                ['amount' => 2, 'unit' => 'Dose', 'food' => 'Tomaten'],
                ['amount' => null, 'unit' => null, 'food' => 'Salz'],
            ],
            ...$overrides,
        ];
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function created(TestResponse $response): Recipe
    {
        return Recipe::query()->findOrFail((int) $response->assertCreated()->json('data.id'));
    }

    public function test_it_saves_a_private_recipe_for_the_user(): void
    {
        $user = User::factory()->create(['admin' => false]);
        Passport::actingAs($user);

        $response = $this->postJson('/api/recipes', $this->payload());
        $recipe = $this->created($response);

        $response->assertExactJson(['data' => [
            'id' => $recipe->id,
            'url' => route('recipes.show', $recipe),
        ]]);

        $this->assertSame('Pasta mit Tomaten', $recipe->name);
        $this->assertSame($user->author_id, $recipe->author_id);
        $this->assertNull($recipe->cookbook_id);
        $this->assertFalse($recipe->is_public);
        $this->assertSame(4.0, $recipe->servings);
        $this->assertSame(Complexity::Normal, $recipe->complexity);
        $this->assertSame(config('services.shoppinglist.default_category'), $recipe->category?->name);
        $this->assertStringContainsString('<ol>', $recipe->instructions);
        $this->assertSame(
            ['Spaghetti', 'Tomaten', 'Salz'],
            $recipe->ingredients->map(fn ($ingredient) => $ingredient->food?->name)->all(),
        );
        $this->assertSame([1, 2, 3], $recipe->ingredients->pluck('position')->all());

        // The owner sees it, others do not.
        $this->getJson('/api/recipes/'.$recipe->id)->assertOk()->assertJsonPath('data.ingredients.0.text', '400 g Spaghetti');
        Passport::actingAs(User::factory()->create(['admin' => false]));
        $this->getJson('/api/recipes/'.$recipe->id)->assertNotFound();
    }

    public function test_existing_foods_and_units_are_matched_case_insensitively(): void
    {
        $spaghetti = Food::factory()->create(['name' => 'Spaghetti']);
        $gram = Unit::factory()->create(['name' => 'Gramm', 'name_shortcut' => 'g', 'name_plural' => null, 'name_plural_shortcut' => null]);
        $can = Unit::factory()->create(['name' => 'Dose', 'name_shortcut' => null, 'name_plural' => 'Dosen', 'name_plural_shortcut' => null]);
        $trashedFood = Food::factory()->create(['name' => 'Tomaten']);
        $trashedFood->delete();

        Passport::actingAs(User::factory()->create());

        $recipe = $this->created($this->postJson('/api/recipes', $this->payload([
            'ingredients' => [
                ['amount' => 400, 'unit' => 'G', 'food' => 'spaghetti'],
                ['amount' => 2, 'unit' => 'dosen', 'food' => 'TOMATEN'],
                ['amount' => 1, 'unit' => 'Prise', 'food' => 'Salz'],
                ['amount' => 1, 'unit' => 'prise', 'food' => 'salz'],
            ],
        ])));

        $ingredients = $recipe->ingredients;

        $this->assertSame($spaghetti->id, $ingredients[0]->food_id);
        $this->assertSame($gram->id, $ingredients[0]->unit_id);
        $this->assertSame($trashedFood->id, $ingredients[1]->food_id);
        $this->assertFalse($trashedFood->fresh()?->trashed());
        $this->assertSame($can->id, $ingredients[1]->unit_id);

        // New foods and units are created once, even when repeated with another case.
        $this->assertSame(1, Food::query()->where('name', 'Salz')->count());
        $this->assertSame(1, Unit::query()->where('name', 'Prise')->count());
        $this->assertSame($ingredients[2]->food_id, $ingredients[3]->food_id);
        $this->assertSame($ingredients[2]->unit_id, $ingredients[3]->unit_id);
    }

    public function test_the_default_category_is_reused(): void
    {
        $category = Category::factory()->create(['name' => config('services.shoppinglist.default_category')]);
        Passport::actingAs(User::factory()->create());

        $recipe = $this->created($this->postJson('/api/recipes', $this->payload()));

        $this->assertSame($category->id, $recipe->category_id);
        $this->assertSame(1, Category::query()->count());
    }

    public function test_raw_html_in_the_instructions_is_escaped(): void
    {
        Passport::actingAs(User::factory()->create());

        $recipe = $this->created($this->postJson('/api/recipes', $this->payload([
            'instructions' => "Kochen <script>alert(1)</script>\n\n[Link](javascript:alert(1))",
        ])));

        $instructions = (string) $recipe->instructions;

        $this->assertStringNotContainsString('<script>', $instructions);
        $this->assertStringNotContainsString('javascript:', $instructions);
    }

    public function test_a_duplicate_name_of_the_same_author_is_rejected(): void
    {
        $user = User::factory()->create();
        Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null, 'name' => 'Pasta mit Tomaten']);
        Passport::actingAs($user);

        $this->postJson('/api/recipes', $this->payload())->assertJsonValidationErrors('name');

        Passport::actingAs(User::factory()->create());
        $this->postJson('/api/recipes', $this->payload())->assertCreated();
    }

    public function test_it_validates_the_body(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->postJson('/api/recipes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'instructions', 'ingredients']);

        $this->postJson('/api/recipes', $this->payload([
            'name' => str_repeat('a', 101),
            'servings' => 0,
            'ingredients' => [
                ['amount' => -1, 'unit' => str_repeat('u', 21), 'food' => ''],
                ['amount' => 'viel', 'food' => str_repeat('f', 51)],
                ['amount' => 5, 'amount_max' => 2, 'food' => 'Mehl'],
            ],
        ]))->assertJsonValidationErrors([
            'name',
            'servings',
            'ingredients.0.amount',
            'ingredients.0.unit',
            'ingredients.0.food',
            'ingredients.1.amount',
            'ingredients.1.food',
            'ingredients.2.amount_max',
        ]);

        $this->postJson('/api/recipes', $this->payload(['ingredients' => []]))->assertJsonValidationErrors('ingredients');
        $this->assertSame(0, Recipe::query()->count());
    }

    public function test_servings_and_amount_max_are_optional(): void
    {
        Passport::actingAs(User::factory()->create());

        $recipe = $this->created($this->postJson('/api/recipes', $this->payload([
            'servings' => null,
            'ingredients' => [['amount' => 1, 'amount_max' => 2, 'unit' => null, 'food' => 'Ei']],
        ])));

        $this->assertNull($recipe->servings);
        $this->assertSame(2.0, (float) $recipe->ingredients[0]->amount_max);
    }
}
