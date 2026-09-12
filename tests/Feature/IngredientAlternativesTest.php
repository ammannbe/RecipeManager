<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientAttribute;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\TestCase;

class IngredientAlternativesTest extends TestCase
{
    private function parent(Recipe $recipe, ?IngredientGroup $group = null, int $position = 1): Ingredient
    {
        return Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => $group?->id,
            'ingredient_id' => null,
            'food_id' => Food::factory(),
            'position' => $position,
        ]);
    }

    private function alternativeOf(Ingredient $parent, ?Food $food = null): Ingredient
    {
        return Ingredient::factory()->create([
            'recipe_id' => $parent->recipe_id,
            'ingredient_group_id' => $parent->ingredient_group_id,
            'ingredient_id' => $parent->id,
            'food_id' => $food === null ? Food::factory() : $food->id,
            'position' => 1,
        ]);
    }

    public function test_alternatives_are_not_listed_as_top_level_ingredients(): void
    {
        $recipe = Recipe::factory()->create();
        $parent = $this->parent($recipe);
        $alternative = $this->alternativeOf($parent);

        $ungrouped = $recipe->ungroupedIngredients()->pluck('id');

        $this->assertTrue($ungrouped->contains($parent->id));
        $this->assertFalse($ungrouped->contains($alternative->id));
    }

    public function test_grouped_alternatives_are_not_listed_as_top_level_ingredients(): void
    {
        $recipe = Recipe::factory()->create();
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $parent = $this->parent($recipe, $group);
        $alternative = $this->alternativeOf($parent);

        $topLevel = $group->topLevelIngredients()->pluck('id');

        $this->assertTrue($topLevel->contains($parent->id));
        $this->assertFalse($topLevel->contains($alternative->id));
        $this->assertTrue($group->ingredients()->whereKey($alternative->id)->exists());
    }

    public function test_the_top_level_repeater_only_holds_the_parent_row(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $parent = $this->parent($recipe);
        $this->alternativeOf($parent);

        $this->actingAs($user);

        $state = Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->get('data')['ungroupedIngredients'];

        $this->assertCount(1, $state);
        $this->assertEquals($parent->food_id, reset($state)['food_id']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $alternatives
     */
    private function callAlternativesAction(Recipe $recipe, Ingredient $parent, array $alternatives): void
    {
        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->callAction(
                TestAction::make('alternatives')
                    ->arguments(['item' => 'record-'.$parent->id])
                    ->schemaComponent('ungroupedIngredients'),
                data: ['alternatives' => $alternatives],
            )
            ->assertHasNoActionErrors();
    }

    public function test_the_action_prefills_the_existing_alternatives(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $parent = $this->parent($recipe);
        $alternative = $this->alternativeOf($parent);

        $this->actingAs($user);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->mountAction(
                TestAction::make('alternatives')
                    ->arguments(['item' => 'record-'.$parent->id])
                    ->schemaComponent('ungroupedIngredients'),
            )
            ->assertActionDataSet(fn (array $data): bool => count($data['alternatives']) === 1
                && (int) reset($data['alternatives'])['id'] === $alternative->id);
    }

    public function test_an_alternative_can_be_added_through_the_action(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $parent = $this->parent($recipe);
        $food = Food::factory()->create();
        $attribute = IngredientAttribute::factory()->create();

        $this->actingAs($user);

        $this->callAlternativesAction($recipe, $parent, [[
            'id' => null,
            'amount' => 2,
            'amount_max' => null,
            'unit_id' => null,
            'food_id' => $food->id,
            'ingredientAttributes' => [$attribute->id],
        ]]);

        $alternative = Ingredient::query()->where('ingredient_id', $parent->id)->firstOrFail();

        $this->assertSame($food->id, $alternative->food_id);
        $this->assertSame($recipe->id, $alternative->recipe_id);
        $this->assertNull($alternative->ingredient_group_id);
        $this->assertSame(1, $alternative->position);
        $this->assertSame([$attribute->id], $alternative->ingredientAttributes->pluck('id')->all());
    }

    public function test_an_existing_alternative_is_updated_rather_than_duplicated(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $parent = $this->parent($recipe);
        $alternative = $this->alternativeOf($parent);
        $food = Food::factory()->create();

        $this->actingAs($user);

        $this->callAlternativesAction($recipe, $parent, [[
            'id' => $alternative->id,
            'amount' => 5,
            'amount_max' => null,
            'unit_id' => null,
            'food_id' => $food->id,
            'ingredientAttributes' => [],
        ]]);

        $this->assertSame(1, $parent->ingredients()->count());
        $this->assertSame($food->id, $alternative->refresh()->food_id);
        $this->assertEquals(5, $alternative->amount);
    }

    public function test_removing_a_row_soft_deletes_the_alternative(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $parent = $this->parent($recipe);
        $alternative = $this->alternativeOf($parent);

        $this->actingAs($user);

        $this->callAlternativesAction($recipe, $parent, []);

        $this->assertSoftDeleted($alternative);
        $this->assertSame(0, $parent->ingredients()->count());
    }

    public function test_an_alternative_can_be_added_to_a_grouped_ingredient(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $parent = $this->parent($recipe, $group);
        $food = Food::factory()->create();

        $this->actingAs($user);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->callAction(
                TestAction::make('alternatives')
                    ->arguments(['item' => 'record-'.$parent->id])
                    ->schemaComponent('ingredientGroups.record-'.$group->id.'.topLevelIngredients'),
                data: ['alternatives' => [[
                    'id' => null,
                    'amount' => 1,
                    'amount_max' => null,
                    'unit_id' => null,
                    'food_id' => $food->id,
                    'ingredientAttributes' => [],
                ]]],
            )
            ->assertHasNoActionErrors();

        $alternative = Ingredient::query()->where('ingredient_id', $parent->id)->firstOrFail();

        $this->assertSame($group->id, $alternative->ingredient_group_id);
        $this->assertSame($recipe->id, $alternative->recipe_id);
    }

    public function test_an_alternative_inherits_the_group_of_its_parent(): void
    {
        $recipe = Recipe::factory()->create();
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $parent = $this->parent($recipe, $group);

        $alternative = Ingredient::factory()->create([
            'recipe_id' => null,
            'ingredient_group_id' => null,
            'ingredient_id' => $parent->id,
            'food_id' => Food::factory(),
            'position' => 1,
        ]);

        $this->assertSame($group->id, $alternative->refresh()->ingredient_group_id);
        $this->assertSame($recipe->id, $alternative->recipe_id);
    }

    public function test_the_public_page_renders_alternatives_below_their_parent(): void
    {
        $recipe = Recipe::factory()->create(['cookbook_id' => null]);

        $parent = Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => null,
            'ingredient_id' => null,
            'food_id' => Food::factory()->create(['name' => 'Butter']),
            'unit_id' => Unit::factory()->create(['name' => 'Gramm']),
            'amount' => 100,
            'position' => 1,
        ]);

        $alternative = $this->alternativeOf($parent, Food::factory()->create(['name' => 'Margarine']));
        $alternative->ingredientAttributes()->sync([
            IngredientAttribute::factory()->create(['name' => 'weich'])->id,
        ]);

        $this->get(route('recipes.show', $recipe))
            ->assertSuccessful()
            ->assertSeeInOrder(['Butter', 'or', 'Margarine', 'weich'], escape: false);
    }

    public function test_the_public_page_does_not_list_an_alternative_as_its_own_ingredient(): void
    {
        $recipe = Recipe::factory()->create(['cookbook_id' => null]);
        $parent = Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => null,
            'ingredient_id' => null,
            'food_id' => Food::factory()->create(['name' => 'Butter']),
            'position' => 1,
        ]);

        $this->alternativeOf($parent, Food::factory()->create(['name' => 'Margarine']));

        $response = $this->get(route('recipes.show', $recipe))->assertSuccessful();

        $this->assertSame(1, substr_count((string) $response->getContent(), 'Margarine'));
    }

    public function test_deleting_a_recipe_also_soft_deletes_alternatives(): void
    {
        $recipe = Recipe::factory()->create();
        $parent = $this->parent($recipe);
        $alternative = $this->alternativeOf($parent);

        $recipe->delete();

        $this->assertSoftDeleted($parent);
        $this->assertSoftDeleted($alternative);
    }

    public function test_deleting_a_group_also_soft_deletes_alternatives_inside_it(): void
    {
        $recipe = Recipe::factory()->create();
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $parent = $this->parent($recipe, $group);
        $alternative = $this->alternativeOf($parent);

        $group->delete();

        $this->assertSoftDeleted($parent);
        $this->assertSoftDeleted($alternative);
    }
}
