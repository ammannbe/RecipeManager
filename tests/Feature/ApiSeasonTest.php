<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiSeasonTest extends TestCase
{
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

    public function test_the_season_month_ranks_recipes_with_more_in_season_foods_first(): void
    {
        $mince = Food::factory()->create();
        $asparagus = Food::factory()->create(['season_months' => [4, 5, 6]]);
        $tomatoes = Food::factory()->create(['season_months' => [7, 8]]);
        $winter = $this->recipeWith(['name' => 'A winter'], [$mince, $tomatoes]);
        $spring = $this->recipeWith(['name' => 'B spring'], [$mince, $asparagus]);
        $both = $this->recipeWith(['name' => 'C both'], [$mince, $asparagus, $tomatoes]);
        $private = $this->recipeWith(['name' => 'D private', 'is_public' => false], [$mince, $asparagus]);

        Passport::actingAs(User::factory()->create(['admin' => false]));

        $ids = fn (array $payload): array => array_column(
            (array) $this->postJson('/api/recipes/search', $payload)->assertOk()->json('data'),
            'id',
        );

        $this->assertSame([$winter->id, $spring->id, $both->id], $ids(['food_ids' => [$mince->id]]));
        $this->assertSame([$spring->id, $both->id, $winter->id], $ids(['food_ids' => [$mince->id], 'season_month' => 5]));
        // Matched foods still come first, in-season foods break the tie.
        $this->assertSame(
            [$both->id, $winter->id, $spring->id],
            $ids(['food_ids' => [$mince->id, $tomatoes->id], 'season_month' => 5]),
        );
        $this->assertNotContains($private->id, $ids(['food_ids' => [$mince->id], 'season_month' => 5]));
    }

    public function test_the_season_month_is_validated(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->postJson('/api/recipes/search', ['food_ids' => [1], 'season_month' => 13])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('season_month');
    }
}
