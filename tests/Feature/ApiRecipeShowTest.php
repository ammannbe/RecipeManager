<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientAttribute;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiRecipeShowTest extends TestCase
{
    private Unit $gram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gram = Unit::factory()->create([
            'name' => 'Gramm',
            'name_shortcut' => 'g',
            'name_plural' => 'Gramm',
            'name_plural_shortcut' => 'g',
        ]);
    }

    /**
     * Lasagne for 2: one ungrouped ingredient, a "Sauce" group with an alternative, and
     * an ingredient with attributes.
     */
    private function lasagne(): Recipe
    {
        $recipe = Recipe::factory()->create([
            'is_public' => true,
            'cookbook_id' => null,
            'name' => 'Lasagne',
            'servings' => 2,
            'serving_type' => 'Portionen',
        ]);

        $sauce = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'name' => 'Sauce', 'position' => 1]);
        $sheets = Food::factory()->create(['name' => 'Lasagneblätter']);
        $mince = Food::factory()->create(['name' => 'Hackfleisch']);
        $tofu = Food::factory()->create(['name' => 'Tofu']);
        $onion = Food::factory()->create(['name' => 'Zwiebel']);
        $piece = Unit::factory()->create([
            'name' => 'Stück',
            'name_shortcut' => 'Stk.',
            'name_plural' => 'Stücke',
            'name_plural_shortcut' => 'Stk.',
        ]);

        Ingredient::factory()->create([
            'recipe_id' => $recipe->id, 'ingredient_group_id' => null, 'position' => 1,
            'amount' => 250, 'amount_max' => null, 'unit_id' => $this->gram->id, 'food_id' => $sheets->id,
        ]);
        $mainMince = Ingredient::factory()->create([
            'recipe_id' => $recipe->id, 'ingredient_group_id' => $sauce->id, 'position' => 1,
            'amount' => 250, 'amount_max' => 300, 'unit_id' => $this->gram->id, 'food_id' => $mince->id,
        ]);
        $mainMince->ingredients()->create([
            'amount' => 200, 'amount_max' => null, 'unit_id' => $this->gram->id, 'food_id' => $tofu->id, 'position' => 1,
        ]);
        $onionIngredient = Ingredient::factory()->create([
            'recipe_id' => $recipe->id, 'ingredient_group_id' => $sauce->id, 'position' => 2,
            'amount' => 0.5, 'amount_max' => null, 'unit_id' => $piece->id, 'food_id' => $onion->id,
        ]);
        $onionIngredient->ingredientAttributes()->attach(IngredientAttribute::factory()->create(['name' => 'gehackt']));

        return $recipe;
    }

    public function test_it_returns_the_ingredients_like_the_recipe_page(): void
    {
        $recipe = $this->lasagne();
        Passport::actingAs(User::factory()->create(['locale' => 'de']));

        $response = $this->getJson('/api/recipes/'.$recipe->id)->assertOk();

        $response->assertJsonPath('data.id', $recipe->id)
            ->assertJsonPath('data.name', 'Lasagne')
            ->assertJsonPath('data.url', route('recipes.show', $recipe))
            ->assertJsonPath('data.servings', 2)
            ->assertJsonPath('data.original_servings', 2)
            ->assertJsonPath('data.serving_type', 'Portionen')
            ->assertJsonMissingPath('data.photos');

        $ingredients = $response->json('data.ingredients');

        $this->assertSame(['250 g Lasagneblätter', '250 - 300 g Hackfleisch', '0,5 Stk. Zwiebel (gehackt)'], array_column($ingredients, 'text'));
        $this->assertSame([null, 'Sauce', 'Sauce'], array_column($ingredients, 'group'));
        $this->assertSame([
            'amount' => 250,
            'amount_max' => 300,
            'unit' => 'g',
            'food_id' => Food::query()->where('name', 'Hackfleisch')->value('id'),
            'food' => 'Hackfleisch',
            'group' => 'Sauce',
            'text' => '250 - 300 g Hackfleisch',
        ], array_diff_key($ingredients[1], ['alternatives' => true]));
        $this->assertSame(['200 g Tofu'], array_column($ingredients[1]['alternatives'], 'text'));
        $this->assertSame([], $ingredients[0]['alternatives']);
    }

    public function test_amounts_are_scaled_to_the_requested_servings(): void
    {
        $recipe = $this->lasagne();
        Passport::actingAs(User::factory()->create(['locale' => 'en']));

        $response = $this->getJson('/api/recipes/'.$recipe->id.'?servings=3')->assertOk();

        $response->assertJsonPath('data.servings', 3)->assertJsonPath('data.original_servings', 2);
        $ingredients = $response->json('data.ingredients');

        $this->assertSame([375, 375, 0.75], array_column($ingredients, 'amount'));
        $this->assertSame([null, 450, null], array_column($ingredients, 'amount_max'));
        $this->assertSame(['375 g Lasagneblätter', '375 - 450 g Hackfleisch', '0.75 Stk. Zwiebel (gehackt)'], array_column($ingredients, 'text'));
        $this->assertSame(300, $ingredients[1]['alternatives'][0]['amount']);
    }

    public function test_recipes_without_servings_are_not_scaled(): void
    {
        $recipe = Recipe::factory()->create(['is_public' => true, 'cookbook_id' => null, 'servings' => null]);
        Ingredient::factory()->create([
            'recipe_id' => $recipe->id, 'ingredient_group_id' => null, 'position' => 1,
            'amount' => 100, 'amount_max' => null, 'unit_id' => $this->gram->id,
        ]);

        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/recipes/'.$recipe->id.'?servings=8')
            ->assertOk()
            ->assertJsonPath('data.servings', null)
            ->assertJsonPath('data.original_servings', null)
            ->assertJsonPath('data.ingredients.0.amount', 100);
    }

    public function test_ingredients_without_amount_or_unit_only_show_the_food(): void
    {
        $recipe = Recipe::factory()->create(['is_public' => true, 'cookbook_id' => null, 'servings' => 2]);
        Ingredient::factory()->create([
            'recipe_id' => $recipe->id, 'ingredient_group_id' => null, 'position' => 1,
            'amount' => null, 'amount_max' => null, 'unit_id' => null,
            'food_id' => Food::factory()->create(['name' => 'Salz'])->id,
        ]);

        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/recipes/'.$recipe->id.'?servings=4')
            ->assertOk()
            ->assertJsonPath('data.ingredients.0.text', 'Salz')
            ->assertJsonPath('data.ingredients.0.amount', null)
            ->assertJsonPath('data.ingredients.0.unit', null);
    }

    public function test_foreign_private_recipes_are_not_found(): void
    {
        $recipe = Recipe::factory()->create(['is_public' => false, 'cookbook_id' => null]);
        Passport::actingAs(User::factory()->create(['admin' => false]));

        $this->getJson('/api/recipes/'.$recipe->id)->assertNotFound();
        // Validation must not reveal that the recipe exists either.
        $this->getJson('/api/recipes/'.$recipe->id.'?servings=0')->assertNotFound();
    }

    public function test_own_private_recipes_are_shown(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $recipe = Recipe::factory()->create(['is_public' => false, 'cookbook_id' => null, 'author_id' => $user->author_id]);
        Passport::actingAs($user);

        $this->getJson('/api/recipes/'.$recipe->id)->assertOk();
    }

    public function test_trashed_recipes_and_ingredients_are_hidden(): void
    {
        $recipe = $this->lasagne();
        $recipe->ingredients()->whereHas('food', fn ($query) => $query->where('name', 'Zwiebel'))->first()?->delete();
        Passport::actingAs(User::factory()->create());

        $this->assertCount(2, $this->getJson('/api/recipes/'.$recipe->id)->assertOk()->json('data.ingredients'));

        $recipe->delete();

        $this->getJson('/api/recipes/'.$recipe->id)->assertNotFound();
    }

    public function test_it_validates_the_servings(): void
    {
        $recipe = $this->lasagne();
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/recipes/'.$recipe->id.'?servings=0.4')->assertJsonValidationErrors('servings');
        $this->getJson('/api/recipes/'.$recipe->id.'?servings=101')->assertJsonValidationErrors('servings');
        $this->getJson('/api/recipes/'.$recipe->id.'?servings=abc')->assertJsonValidationErrors('servings');
        $this->getJson('/api/recipes/'.$recipe->id.'?servings=0.5')->assertOk();
    }
}
