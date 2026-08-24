<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeIngredientMoveTest extends TestCase
{
    private function ingredient(Recipe $recipe, ?IngredientGroup $group, int $position = 1): Ingredient
    {
        return Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => $group?->id,
            'ingredient_id' => null,
            'food_id' => Food::factory(),
            'position' => $position,
        ]);
    }

    public function test_an_ungrouped_ingredient_can_be_moved_into_a_group(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $ingredient = $this->ingredient($recipe, null);

        $this->actingAs($user);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->callAction(
                TestAction::make('moveToGroup')
                    ->arguments(['item' => 'record-'.$ingredient->id])
                    ->schemaComponent('ungroupedIngredients'),
                data: ['ingredient_group_id' => $group->id],
            );

        $this->assertSame($group->id, $ingredient->refresh()->ingredient_group_id);
    }

    public function test_a_grouped_ingredient_can_be_moved_out_of_its_group(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $ingredient = $this->ingredient($recipe, $group);

        $this->actingAs($user);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->callAction(
                TestAction::make('moveToGroup')
                    ->arguments(['item' => 'record-'.$ingredient->id])
                    ->schemaComponent('ungroupedIngredients'),
                data: ['ingredient_group_id' => null],
            );

        $this->assertNull($ingredient->refresh()->ingredient_group_id);
    }

    public function test_a_moved_ingredient_is_appended_to_the_target_group(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);

        $this->ingredient($recipe, $group, position: 1);
        $this->ingredient($recipe, $group, position: 2);
        $moved = $this->ingredient($recipe, null, position: 1);

        $this->actingAs($user);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->callAction(
                TestAction::make('moveToGroup')
                    ->arguments(['item' => 'record-'.$moved->id])
                    ->schemaComponent('ungroupedIngredients'),
                data: ['ingredient_group_id' => $group->id],
            );

        $this->assertSame(3, $moved->refresh()->position);
    }

    public function test_alternatives_follow_their_parent(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $parent = $this->ingredient($recipe, null);

        $alternative = Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => null,
            'ingredient_id' => $parent->id,
            'food_id' => Food::factory(),
            'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->callAction(
                TestAction::make('moveToGroup')
                    ->arguments(['item' => 'record-'.$parent->id])
                    ->schemaComponent('ungroupedIngredients'),
                data: ['ingredient_group_id' => $group->id],
            );

        $this->assertSame($group->id, $alternative->refresh()->ingredient_group_id);
    }

    public function test_an_ingredient_can_be_moved_between_two_groups(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $from = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $to = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 2]);
        $ingredient = $this->ingredient($recipe, $from);

        $this->actingAs($user);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->callAction(
                TestAction::make('moveToGroup')
                    ->arguments(['item' => 'record-'.$ingredient->id])
                    ->schemaComponent('ingredientGroups.record-'.$from->id.'.ingredients'),
                data: ['ingredient_group_id' => $to->id],
            );

        $this->assertSame($to->id, $ingredient->refresh()->ingredient_group_id);
    }

    public function test_an_ingredient_cannot_be_moved_into_another_recipes_group(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $foreignGroup = IngredientGroup::factory()->create(['position' => 1]);
        $ingredient = $this->ingredient($recipe, null);

        $this->actingAs($user);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->callAction(
                TestAction::make('moveToGroup')
                    ->arguments(['item' => 'record-'.$ingredient->id])
                    ->schemaComponent('ungroupedIngredients'),
                data: ['ingredient_group_id' => $foreignGroup->id],
            );

        $this->assertNull($ingredient->refresh()->ingredient_group_id);
    }
}
