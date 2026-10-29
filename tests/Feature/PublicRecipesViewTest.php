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
            'tags' => new Collection,
            'cookbooks' => new Collection,
            'search' => '',
            'quick' => false,
            'complexity' => '',
            'selectedCategory' => null,
            'selectedCookbook' => null,
            'selectedTags' => [],
            'selectedSort' => 'created_at_desc',
            'paginationPages' => [],
            'currentPage' => 1,
        ]);

        $view->assertSee(__('No recipes found.'));
        $view->assertSee(__('Reset'));
        $view->assertDontSee(__('Apply'));
    }

    public function test_recipes_index_filters_are_collapsed_unless_a_filter_is_active(): void
    {
        $data = [
            'recipes' => new LengthAwarePaginator(items: [], total: 0, perPage: 15, currentPage: 1, options: ['path' => '/']),
            'categories' => new Collection,
            'tags' => new Collection,
            'cookbooks' => new Collection,
            'search' => '',
            'quick' => false,
            'complexity' => '',
            'selectedCategory' => null,
            'selectedCookbook' => null,
            'selectedTags' => [],
            'selectedSort' => 'created_at_desc',
            'paginationPages' => [],
            'currentPage' => 1,
        ];

        $collapsed = $this->view('recipes.index', $data);
        $collapsed->assertSee('x-data="{ open: false }"', false);
        $collapsed->assertSee(__('Filters'));

        $expanded = $this->view('recipes.index', [...$data, 'search' => 'pasta']);
        $expanded->assertSee('x-data="{ open: true }"', false);
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

        $view->assertSee('aspect-16/10', false);
        $view->assertSee(__('Previous image'));
        $view->assertSee(__('Next image'));
        $view->assertSee(__('Recipe images'));
    }

    public function test_recipe_show_sanitizes_the_instructions(): void
    {
        $recipe = new Recipe([
            'name' => 'Test recipe',
            'instructions' => '<p><strong>Kochen</strong> <a href="javascript:alert(1)">Link</a></p><script>alert(2)</script><img src="x" onerror="alert(3)">',
            'cookbook_id' => null,
            'photos' => [],
        ]);

        $recipe->updated_at = now();
        $recipe->setRelation('author', new Author(['name' => 'Author name']));
        $recipe->setRelation('category', new Category(['name' => 'Category name']));
        $recipe->setRelation('cookbook', null);
        $recipe->setRelation('ingredients', new Collection);
        $recipe->setRelation('ingredientGroups', new Collection);

        $view = $this->view('recipes.show', ['recipe' => $recipe]);

        $view->assertSee('<strong>Kochen</strong>', false);
        $view->assertDontSee('alert(1)', false);
        $view->assertDontSee('alert(2)', false);
        $view->assertDontSee('onerror', false);
    }
}
