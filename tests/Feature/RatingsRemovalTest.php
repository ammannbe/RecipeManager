<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RatingsRemovalTest extends TestCase
{
    public function test_the_rating_tables_are_gone(): void
    {
        $this->assertFalse(Schema::hasTable('ratings'));
        $this->assertFalse(Schema::hasTable('rating_criteria'));
    }

    public function test_the_public_recipe_list_renders_without_rating_aggregates(): void
    {
        Recipe::factory()->create(['cookbook_id' => null]);

        $this->get(route('recipes.index'))
            ->assertOk()
            ->assertDontSee('★', escape: false);
    }

    public function test_the_admin_recipe_list_renders_without_rating_aggregates(): void
    {
        $user = User::factory()->create(['admin' => true]);
        Recipe::factory()->create(['author_id' => $user->author_id]);

        $this->actingAs($user)
            ->get('/admin/recipes')
            ->assertOk();
    }

    public function test_deleting_a_recipe_still_cascades_to_ingredients_and_groups(): void
    {
        $recipe = Recipe::factory()->create();
        $group = IngredientGroup::factory()->create(['recipe_id' => $recipe->id]);
        $ungrouped = Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => null,
        ]);
        $grouped = Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => $group->id,
        ]);

        $recipe->delete();

        $this->assertSoftDeleted($recipe);
        $this->assertSoftDeleted($group);
        $this->assertSoftDeleted($ungrouped);
        $this->assertSoftDeleted($grouped);
    }
}
