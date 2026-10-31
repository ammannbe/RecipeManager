<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SeasonalRecipesTest extends TestCase
{
    private Food $asparagus;

    private Food $tomatoes;

    private Food $potatoes;

    private Food $salt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asparagus = Food::factory()->create(['name' => 'Spargel', 'season_months' => [4, 5, 6]]);
        $this->tomatoes = Food::factory()->create(['name' => 'Tomaten', 'season_months' => [6, 7, 8, 9]]);
        $this->potatoes = Food::factory()->create([
            'name' => 'Kartoffeln',
            'season_months' => [6, 7, 8, 9, 10],
            'storage_months' => [11, 12, 1, 2, 3, 4, 5],
        ]);
        $this->salt = Food::factory()->create(['name' => 'Salz']);

        // May in Zurich.
        $this->travelTo(Carbon::parse('2026-05-15 12:00:00', 'Europe/Zurich'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, Food>  $foods
     */
    private function recipeWith(array $attributes, array $foods): Recipe
    {
        $recipe = Recipe::factory()->create(['cookbook_id' => null, 'is_public' => true, ...$attributes]);

        foreach (array_values($foods) as $position => $food) {
            Ingredient::factory()->create([
                'recipe_id' => $recipe->id,
                'food_id' => $food->id,
                'ingredient_group_id' => null,
                'position' => $position + 1,
            ]);
        }

        return $recipe;
    }

    public function test_the_scope_needs_one_food_in_season_and_at_least_half_of_those_with_data(): void
    {
        $onlyOne = $this->recipeWith(['name' => 'Spargel mit Salz'], [$this->asparagus, $this->salt]);
        $half = $this->recipeWith(['name' => 'Spargel mit Tomaten'], [$this->asparagus, $this->tomatoes]);
        $third = $this->recipeWith(['name' => 'Spargel-Eintopf'], [$this->asparagus, $this->tomatoes, $this->potatoes]);
        $this->recipeWith(['name' => 'Salzwasser'], [$this->salt]);
        $this->recipeWith(['name' => 'Tomatensalat'], [$this->tomatoes]);

        $this->assertEqualsCanonicalizing(
            [$onlyOne->id, $half->id],
            Recipe::query()->inSeason(5)->pluck('id')->all(),
        );
        $this->assertContains($third->id, Recipe::query()->inSeason(6)->pluck('id')->all());
    }

    public function test_the_index_filters_seasonal_recipes_of_the_current_month(): void
    {
        $this->recipeWith(['name' => 'Spargel mit Salz'], [$this->asparagus, $this->salt]);
        $this->recipeWith(['name' => 'Tomatensalat'], [$this->tomatoes]);

        $this->get('/?seasonal=1')
            ->assertOk()
            ->assertSee('Spargel mit Salz')
            ->assertDontSee('Tomatensalat');

        $this->get('/')->assertSee('Tomatensalat');
    }

    public function test_the_season_page_ranks_visible_recipes_by_in_season_foods(): void
    {
        $spring = Food::factory()->create(['name' => 'Rhabarber', 'season_months' => [4, 5, 6]]);
        $this->recipeWith(['name' => 'Spargel mit Salz'], [$this->asparagus, $this->salt]);
        $this->recipeWith(['name' => 'Frühlingsteller'], [$this->asparagus, $spring, $this->tomatoes]);
        $this->recipeWith(['name' => 'Geheimer Spargel', 'is_public' => false], [$this->asparagus]);
        $this->recipeWith(['name' => 'Tomatensalat'], [$this->tomatoes]);

        $this->get(route('recipes.season'))
            ->assertOk()
            ->assertSeeInOrder(['Frühlingsteller', '2 ingredients in season', 'Spargel mit Salz', '1 ingredient in season'])
            ->assertDontSee('Geheimer Spargel')
            ->assertDontSee('Tomatensalat');
    }

    public function test_the_season_page_lists_fresh_and_storage_foods_of_the_month(): void
    {
        $response = $this->get(route('recipes.season', ['month' => 5]))->assertOk();

        $html = (string) $response->getContent();
        $fresh = (string) strstr((string) strstr($html, 'data-fresh-foods'), 'data-storage-foods', true);
        $this->assertStringContainsString('Spargel', $fresh);
        $this->assertStringNotContainsString('Kartoffeln', $fresh);
        $this->assertStringContainsString('Kartoffeln', (string) strstr($html, 'data-storage-foods'));

        $this->get(route('recipes.season', ['month' => 99]))->assertOk()->assertSee('In season in May');
        $this->get(route('recipes.season', ['month' => 8]))->assertOk()->assertSee('In season in August');
    }

    public function test_the_season_page_shows_private_recipes_to_their_author(): void
    {
        $recipe = $this->recipeWith(['name' => 'Geheimer Spargel', 'is_public' => false], [$this->asparagus]);
        $user = User::factory()->create(['admin' => false, 'author_id' => $recipe->author_id]);

        $this->actingAs($user)->get(route('recipes.season'))->assertOk()->assertSee('Geheimer Spargel');
    }

    public function test_the_recipe_page_shows_the_season_badge(): void
    {
        $recipe = $this->recipeWith(['name' => 'Spargel mit Tomaten'], [$this->asparagus, $this->tomatoes, $this->salt]);

        $this->get(route('recipes.show', $recipe))
            ->assertOk()
            // Only the foods in season are named (tomatoes are not in May).
            ->assertSee('In season: Spargel')
            ->assertDontSee('In season: Spargel, Tomaten');
    }

    public function test_the_season_badge_is_hidden_without_foods_in_season(): void
    {
        $recipe = $this->recipeWith(['name' => 'Salzwasser'], [$this->salt]);
        $this->get(route('recipes.show', $recipe))->assertOk()->assertDontSee('data-season-badge', false);

        $recipe = $this->recipeWith(['name' => 'Tomatensalat'], [$this->tomatoes, $this->salt]);
        $this->get(route('recipes.show', $recipe))->assertOk()->assertDontSee('data-season-badge', false);
    }
}
