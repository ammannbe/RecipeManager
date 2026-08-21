<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Models\Author;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeAuthorFieldTest extends TestCase
{
    public function test_admin_can_set_author_on_recipe_creation(): void
    {
        $admin = User::factory()->create(['admin' => true]);
        $author = Author::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin);

        Livewire::test(CreateRecipe::class)
            ->assertFormFieldExists('author_id')
            ->fillForm([
                'author_id' => $author->id,
                'category_id' => $category->id,
                'name' => 'Admin created recipe',
                'complexity' => 'simple',
                'instructions' => 'Do stuff',
                'ungroupedIngredients' => [],
                'ingredientGroups' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Recipe::class, [
            'name' => 'Admin created recipe',
            'author_id' => $author->id,
        ]);
    }

    public function test_non_admin_cannot_see_author_field_and_gets_own_author_assigned(): void
    {
        $author = Author::factory()->create();
        $user = User::factory()->create(['admin' => false, 'author_id' => $author->id]);
        $category = Category::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateRecipe::class)
            ->assertFormFieldDoesNotExist('author_id')
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Author created recipe',
                'complexity' => 'simple',
                'instructions' => 'Do stuff',
                'ungroupedIngredients' => [],
                'ingredientGroups' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Recipe::class, [
            'name' => 'Author created recipe',
            'author_id' => $author->id,
        ]);
    }
}
