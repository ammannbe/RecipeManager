<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientAttribute;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeTrashTest extends TestCase
{
    private function ownRecipe(User $user, string $name = 'Apfelkuchen'): Recipe
    {
        return Recipe::factory()->create(['name' => $name, 'cookbook_id' => null, 'author_id' => $user->author_id]);
    }

    private function ingredient(Recipe $recipe, ?IngredientGroup $group, int $position): Ingredient
    {
        return Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => $group?->id,
            'ingredient_id' => null,
            'food_id' => Food::factory(),
            'position' => $position,
        ]);
    }

    public function test_the_trash_tab_lists_only_trashed_recipes_the_user_may_see(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $live = $this->ownRecipe($user, 'Lebendig');
        $trashed = $this->ownRecipe($user, 'Weggeworfen');
        $trashed->delete();
        $foreign = Recipe::factory()->create(['cookbook_id' => null, 'is_public' => true]);
        $foreign->delete();

        Livewire::test(ListRecipes::class)
            ->assertCanSeeTableRecords([$live])
            ->assertCanNotSeeTableRecords([$trashed, $foreign])
            ->set('activeTab', ListRecipes::TRASH_TAB)
            ->assertCanSeeTableRecords([$trashed])
            ->assertCanNotSeeTableRecords([$live, $foreign])
            ->assertActionVisible(TestAction::make('restore')->table($trashed))
            ->assertActionVisible(TestAction::make('forceDelete')->table($trashed))
            ->assertActionHidden(TestAction::make('edit')->table($trashed));
    }

    public function test_restoring_brings_back_the_ingredients_in_their_order(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $recipe = $this->ownRecipe($user);
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $first = $this->ingredient($recipe, null, 1);
        $second = $this->ingredient($recipe, null, 2);
        $grouped = $this->ingredient($recipe, $group, 1);
        $removedEarlier = $this->ingredient($recipe, null, 3);
        $removedEarlier->delete();
        $this->travel(1)->minute();
        $recipe->delete();

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($group);

        Livewire::test(ListRecipes::class)
            ->set('activeTab', ListRecipes::TRASH_TAB)
            ->callAction(TestAction::make('restore')->table($recipe))
            ->assertNotified();

        $this->assertNotSoftDeleted($recipe);
        $this->assertEquals(
            [$first->id => 1, $second->id => 2, $grouped->id => 1],
            $recipe->ingredients()->pluck('position', 'id')->all(),
        );
        $this->assertNotSoftDeleted($group);
        $this->assertSoftDeleted($removedEarlier);
    }

    public function test_force_deleting_removes_photos_ingredients_and_pivots(): void
    {
        Storage::fake('recipes');
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $recipe = $this->ownRecipe($user);
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id, 'position' => 1]);
        $ingredient = $this->ingredient($recipe, $group, 1);
        $ingredient->ingredientAttributes()->attach(IngredientAttribute::factory()->create());
        $recipe->tags()->attach(Tag::factory()->create());
        Storage::disk('recipes')->put($recipe->id.'/photo.jpg', 'binary');
        $other = $this->ownRecipe($user, 'Zopf');
        Storage::disk('recipes')->put($other->id.'/photo.jpg', 'binary');
        $recipe->delete();

        // Deleting only moves the recipe to the trash, with its photos.
        Storage::disk('recipes')->assertExists($recipe->id.'/photo.jpg');

        Livewire::test(ListRecipes::class)
            ->set('activeTab', ListRecipes::TRASH_TAB)
            ->callAction(TestAction::make('forceDelete')->table($recipe));

        $this->assertModelMissing($recipe);
        $this->assertModelMissing($ingredient);
        $this->assertModelMissing($group);
        $this->assertDatabaseMissing('recipe_tag', ['recipe_id' => $recipe->id]);
        $this->assertDatabaseMissing('ingredient_ingredient_attribute', ['ingredient_id' => $ingredient->id]);
        Storage::disk('recipes')->assertMissing((string) $recipe->id);
        Storage::disk('recipes')->assertExists($other->id.'/photo.jpg');
    }

    public function test_restoring_and_force_deleting_need_the_delete_right(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $cookbook = Cookbook::factory()->create();
        $cookbook->members()->attach($user->id, ['can_read' => true]);
        $recipe = Recipe::factory()->create(['cookbook_id' => $cookbook->id, 'author_id' => $cookbook->author_id]);
        $recipe->delete();

        Livewire::test(ListRecipes::class)
            ->set('activeTab', ListRecipes::TRASH_TAB)
            ->assertCanSeeTableRecords([$recipe])
            ->assertActionHidden(TestAction::make('restore')->table($recipe))
            ->assertActionHidden(TestAction::make('forceDelete')->table($recipe))
            ->selectTableRecords([$recipe->id])
            ->callAction(TestAction::make('forceDelete')->table()->bulk())
            ->selectTableRecords([$recipe->id])
            ->callAction(TestAction::make('restore')->table()->bulk());

        $this->assertSoftDeleted($recipe);

        $live = Recipe::factory()->create(['cookbook_id' => $cookbook->id, 'author_id' => $cookbook->author_id]);

        Livewire::test(ListRecipes::class)
            ->selectTableRecords([$live->id])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertNotSoftDeleted($live);
    }

    public function test_a_taken_name_blocks_restoring_until_the_recipe_gets_another_one(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $trashed = $this->ownRecipe($user);
        $trashed->delete();
        // Without cookbook the database lets the name repeat, e.g. a recipe created by hand.
        $this->ownRecipe($user);

        $this->assertFalse($trashed->restore());

        Livewire::test(ListRecipes::class)
            ->set('activeTab', ListRecipes::TRASH_TAB)
            ->callAction(TestAction::make('restore')->table($trashed))
            ->assertNotified(__('Recipe could not be restored'))
            ->assertActionVisible(TestAction::make('restoreUnderAnotherName')->table($trashed))
            ->mountAction(TestAction::make('restoreUnderAnotherName')->table($trashed))
            ->assertSchemaStateSet(['name' => 'Apfelkuchen (2)'])
            ->fillForm(['name' => 'Apfelkuchen'])
            ->callMountedAction()
            ->assertHasActionErrors(['name'])
            ->fillForm(['name' => 'Apfelkuchen vom Vorjahr'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified(__('Recipe restored'));

        $trashed->refresh();
        $this->assertFalse($trashed->trashed());
        $this->assertSame('Apfelkuchen vom Vorjahr', $trashed->name);
    }

    public function test_a_trashed_recipe_is_read_only(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $recipe = $this->ownRecipe($user);
        $recipe->delete();

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->assertSuccessful()
            ->assertFormFieldIsDisabled('name')
            ->call('save')
            ->assertNotified(__('Restore the recipe to edit it.'));

        $this->assertSame('Apfelkuchen', $recipe->fresh()?->name);
    }
}
