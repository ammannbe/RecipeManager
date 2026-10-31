<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Ingredient;
use App\Models\Recipe;
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

    public function test_the_season_calendar_lists_the_foods_of_the_month_first(): void
    {
        Food::factory()->create(['name' => 'Aprikosen', 'season_months' => [7, 8]]);

        $html = (string) $this->get(route('recipes.season', ['month' => 5]))->assertOk()->assertSee('data-season-calendar', false)->getContent();
        preg_match_all('/data-food="([^"]+)"/', $html, $rows);

        // May: in season (Spargel), then from storage (Kartoffeln), then the rest by name.
        $this->assertSame(['Spargel', 'Kartoffeln', 'Aprikosen', 'Tomaten'], $rows[1]);
        $this->assertStringContainsString('seasonal=1', $html);
        $this->assertStringNotContainsString('Salz', $html);

        // Invalid months fall back to the current one.
        $this->get(route('recipes.season', ['month' => 99]))->assertOk()->assertSee('in May.');
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
