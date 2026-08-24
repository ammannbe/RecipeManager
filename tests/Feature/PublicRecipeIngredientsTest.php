<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientAttribute;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\Unit;
use Tests\TestCase;

class PublicRecipeIngredientsTest extends TestCase
{
    public function test_it_shows_ingredient_attributes_behind_the_food(): void
    {
        $recipe = Recipe::factory()->create(['cookbook_id' => null]);

        $ingredient = Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => null,
            'ingredient_id' => null,
            'food_id' => Food::factory()->create(['name' => 'Apfel']),
            'unit_id' => Unit::factory()->create(['name' => 'Stück']),
            'position' => 1,
        ]);

        $ingredient->ingredientAttributes()->sync([
            IngredientAttribute::factory()->create(['name' => 'säuerlich'])->id,
            IngredientAttribute::factory()->create(['name' => 'geschält'])->id,
        ]);

        $this->get(route('recipes.show', $recipe))
            ->assertSuccessful()
            ->assertSeeInOrder(['Apfel', 'säuerlich, geschält'], escape: false);
    }

    public function test_it_shows_ingredient_attributes_inside_a_group(): void
    {
        $recipe = Recipe::factory()->create(['cookbook_id' => null]);

        $group = IngredientGroup::factory()->create([
            'recipe_id' => $recipe->id,
            'name' => 'Teig',
            'position' => 1,
        ]);

        $ingredient = Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => $group->id,
            'ingredient_id' => null,
            'food_id' => Food::factory()->create(['name' => 'Butter']),
            'position' => 1,
        ]);

        $ingredient->ingredientAttributes()->sync([
            IngredientAttribute::factory()->create(['name' => 'weich'])->id,
        ]);

        $this->get(route('recipes.show', $recipe))
            ->assertSuccessful()
            ->assertSeeInOrder(['Butter', 'weich'], escape: false);
    }

    public function test_an_ingredient_without_attributes_renders_no_brackets(): void
    {
        $recipe = Recipe::factory()->create(['cookbook_id' => null]);

        Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => null,
            'ingredient_id' => null,
            'food_id' => Food::factory()->create(['name' => 'Salz']),
            'position' => 1,
        ]);

        $this->get(route('recipes.show', $recipe))
            ->assertSuccessful()
            ->assertSee('Salz')
            ->assertDontSee('dark:text-zinc-400">(', escape: false);
    }
}
