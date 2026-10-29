<?php

namespace Tests\Feature;

use App\Models\Cookbook;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiRecipeSearchTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, Food>  $foods
     */
    private function recipeWith(array $attributes, array $foods): Recipe
    {
        $recipe = Recipe::factory()->create(['cookbook_id' => null, ...$attributes]);

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

    /**
     * @param  array<int, int>  $foodIds
     * @return array<int, int>
     */
    private function searchIds(User $user, array $foodIds, ?int $limit = null): array
    {
        Passport::actingAs($user);

        /** @var array<int, array{id: int}> $data */
        $data = $this->postJson('/api/recipes/search', array_filter([
            'food_ids' => $foodIds,
            'limit' => $limit,
        ]))->assertOk()->json('data');

        return array_column($data, 'id');
    }

    public function test_it_returns_the_contract_shape(): void
    {
        $mince = Food::factory()->create();
        $tomato = Food::factory()->create();
        $recipe = $this->recipeWith([
            'is_public' => true,
            'name' => 'Lasagne',
            'servings' => 4,
            'serving_type' => 'Portionen',
            'preparation_time' => '01:15:00',
        ], [$tomato, $mince]);

        Passport::actingAs(User::factory()->create());

        $this->postJson('/api/recipes/search', ['food_ids' => [$mince->id]])
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $recipe->id,
                'name' => 'Lasagne',
                'url' => route('recipes.show', $recipe),
                'servings' => 4,
                'serving_type' => 'Portionen',
                'preparation_time' => '01:15',
                'matched_food_ids' => [$mince->id],
                'food_ids' => collect([$mince->id, $tomato->id])->sort()->values()->all(),
            ]]]);
    }

    public function test_it_ranks_by_matched_foods_then_name(): void
    {
        [$a, $b, $c] = Food::factory()->count(3)->create()->all();

        $one = $this->recipeWith(['is_public' => true, 'name' => 'Alpha'], [$a]);
        $three = $this->recipeWith(['is_public' => true, 'name' => 'Zeta'], [$a, $b, $c]);
        $twoB = $this->recipeWith(['is_public' => true, 'name' => 'Omega'], [$b, $c]);
        $twoA = $this->recipeWith(['is_public' => true, 'name' => 'Beta'], [$a, $c]);
        $this->recipeWith(['is_public' => true, 'name' => 'Unrelated'], [Food::factory()->create()]);

        $this->assertSame(
            [$three->id, $twoA->id, $twoB->id, $one->id],
            $this->searchIds(User::factory()->create(), [$a->id, $b->id, $c->id]),
        );
    }

    public function test_the_same_food_used_twice_counts_once(): void
    {
        [$a, $b] = Food::factory()->count(2)->create()->all();

        $twice = $this->recipeWith(['is_public' => true, 'name' => 'Alpha'], [$a, $a, $a]);
        $both = $this->recipeWith(['is_public' => true, 'name' => 'Beta'], [$a, $b]);

        $this->assertSame([$both->id, $twice->id], $this->searchIds(User::factory()->create(), [$a->id, $b->id]));
    }

    public function test_it_finds_foods_in_ingredient_groups_and_alternatives(): void
    {
        [$grouped, $alternative, $other] = Food::factory()->count(3)->create()->all();

        $recipe = $this->recipeWith(['is_public' => true], [$other]);
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $parent = Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => $group->id,
            'food_id' => Food::factory()->create()->id,
            'position' => 1,
        ]);
        Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => $group->id,
            'food_id' => $grouped->id,
            'position' => 2,
        ]);
        $parent->ingredients()->create(['food_id' => $alternative->id, 'position' => 1]);

        $user = User::factory()->create();

        $this->assertSame([$recipe->id], $this->searchIds($user, [$grouped->id]));
        $this->assertSame([$recipe->id], $this->searchIds($user, [$alternative->id]));
    }

    public function test_foreign_private_recipes_never_appear(): void
    {
        $food = Food::factory()->create();
        $user = User::factory()->create(['admin' => false]);

        $own = $this->recipeWith(['is_public' => false, 'author_id' => $user->author_id, 'name' => 'Own'], [$food]);
        $public = $this->recipeWith(['is_public' => true, 'name' => 'Public'], [$food]);
        $this->recipeWith(['is_public' => false, 'name' => 'Foreign'], [$food]);

        $publishedCookbook = Cookbook::factory()->create(['is_public' => true]);
        $viaCookbook = $this->recipeWith(['is_public' => false, 'cookbook_id' => $publishedCookbook->id, 'name' => 'Published cookbook'], [$food]);

        $sharedCookbook = Cookbook::factory()->create(['is_public' => false]);
        $sharedCookbook->members()->attach($user->id, ['can_read' => true]);
        $shared = $this->recipeWith(['is_public' => false, 'cookbook_id' => $sharedCookbook->id, 'name' => 'Shared'], [$food]);

        $privateCookbook = Cookbook::factory()->create(['is_public' => false]);
        $this->recipeWith(['is_public' => false, 'cookbook_id' => $privateCookbook->id, 'name' => 'Private cookbook'], [$food]);

        $ids = $this->searchIds($user, [$food->id], 50);

        $this->assertEqualsCanonicalizing([$own->id, $public->id, $viaCookbook->id, $shared->id], $ids);
    }

    public function test_trashed_recipes_and_ingredients_are_ignored(): void
    {
        [$food, $other] = Food::factory()->count(2)->create()->all();

        $this->recipeWith(['is_public' => true], [$food])->delete();

        $recipe = $this->recipeWith(['is_public' => true], [$other]);
        Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'food_id' => $food->id,
            'ingredient_group_id' => null,
            'position' => 2,
        ])->delete();

        $this->assertSame([], $this->searchIds(User::factory()->create(), [$food->id]));
    }

    public function test_the_limit_defaults_to_20_and_is_respected(): void
    {
        $food = Food::factory()->create();

        for ($i = 0; $i < 22; $i++) {
            $this->recipeWith(['is_public' => true], [$food]);
        }

        $user = User::factory()->create();

        $this->assertCount(20, $this->searchIds($user, [$food->id]));
        $this->assertCount(5, $this->searchIds($user, [$food->id], 5));
    }

    public function test_it_validates_the_body(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->postJson('/api/recipes/search', [])->assertUnprocessable()->assertJsonValidationErrors('food_ids');
        $this->postJson('/api/recipes/search', ['food_ids' => []])->assertJsonValidationErrors('food_ids');
        $this->postJson('/api/recipes/search', ['food_ids' => ['x']])->assertJsonValidationErrors('food_ids.0');
        $this->postJson('/api/recipes/search', ['food_ids' => range(1, 201)])->assertJsonValidationErrors('food_ids');
        $this->postJson('/api/recipes/search', ['food_ids' => [1], 'limit' => 0])->assertJsonValidationErrors('limit');
        $this->postJson('/api/recipes/search', ['food_ids' => [1], 'limit' => 51])->assertJsonValidationErrors('limit');
    }
}
