<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Category;
use App\Models\Recipe;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PublicRecipesViewTest extends TestCase
{
    public function test_recipes_index_view_renders_successfully(): void
    {
        $recipes = new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: 15,
            currentPage: 1,
            options: ['path' => '/'],
        );

        $view = $this->view('recipes.index', [
            'recipes' => $recipes,
            'categories' => new Collection,
            'search' => '',
            'quick' => false,
            'complexity' => '',
            'selectedCategory' => null,
            'selectedSort' => 'created_at_desc',
            'paginationPages' => [],
            'currentPage' => 1,
        ]);

        $view->assertSee(__('No recipes found.'));
        $view->assertSee(__('Reset'));
        $view->assertDontSee(__('Apply'));
    }

    public function test_recipe_show_displays_slideshow_controls_when_recipe_has_multiple_photos(): void
    {
        $recipe = new Recipe([
            'name' => 'Test recipe',
            'instructions' => '<p>Test instructions</p>',
            'cookbook_id' => null,
            'photos' => ['first-image.jpg', 'second-image.jpg'],
        ]);

        $recipe->updated_at = now();
        $recipe->setRelation('author', new Author(['name' => 'Author name']));
        $recipe->setRelation('category', new Category(['name' => 'Category name']));
        $recipe->setRelation('cookbook', null);
        $recipe->setRelation('ingredients', new Collection);
        $recipe->setRelation('ingredientGroups', new Collection);

        $view = $this->view('recipes.show', [
            'recipe' => $recipe,
        ]);

        $view->assertSee('aspect-[4/3]', false);
        $view->assertSee(__('Previous image'));
        $view->assertSee(__('Next image'));
        $view->assertSee(__('Recipe images'));
    }
}
