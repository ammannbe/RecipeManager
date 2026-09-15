<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeVisibilityDefaultTest extends TestCase
{
    public function test_a_new_recipe_without_a_cookbook_defaults_to_public(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $category = Category::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateRecipe::class)
            ->assertFormSet(['is_public' => true]);
    }

    public function test_a_new_recipe_in_a_private_cookbook_defaults_to_private(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $user->author_id, 'is_public' => false]);
        $category = Category::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateRecipe::class)
            ->fillForm(['cookbook_id' => $cookbook->id])
            ->assertFormSet(['is_public' => false]);
    }

    public function test_a_new_recipe_in_a_public_cookbook_defaults_to_public(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $user->author_id, 'is_public' => true]);
        $category = Category::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateRecipe::class)
            ->fillForm(['cookbook_id' => $cookbook->id])
            ->assertFormSet(['is_public' => true]);
    }

    public function test_the_user_can_still_override_the_default(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $category = Category::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateRecipe::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Overridden visibility',
                'complexity' => 'simple',
                'instructions' => 'Do stuff',
                'is_public' => false,
                'ungroupedIngredients' => [],
                'ingredientGroups' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Recipe::class, [
            'name' => 'Overridden visibility',
            'is_public' => false,
        ]);
    }
}
