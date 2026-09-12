<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Foods\FoodResource;
use App\Filament\Resources\IngredientAttributes\IngredientAttributeResource;
use App\Filament\Resources\Units\UnitResource;
use App\Models\Category;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientAttribute;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The shared lookup resources delegate authorization to their policies instead of
 * repeating `user()->admin` checks. These tests pin that behaviour down.
 *
 * @return array<string, array{0: class-string, 1: class-string}>
 */
class LookupResourceAuthorizationTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: class-string}>
     */
    public static function lookupResources(): array
    {
        return [
            'category' => [CategoryResource::class, Category::class],
            'food' => [FoodResource::class, Food::class],
            'unit' => [UnitResource::class, Unit::class],
            'attribute' => [IngredientAttributeResource::class, IngredientAttribute::class],
        ];
    }

    /**
     * @param  class-string  $resource
     * @param  class-string  $model
     */
    #[DataProvider('lookupResources')]
    public function test_a_non_admin_cannot_create_or_edit_a_lookup(string $resource, string $model): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]));

        $record = $model::factory()->create();

        $this->assertFalse($resource::canCreate());
        $this->assertFalse($resource::canEdit($record));
        $this->assertFalse($resource::canDelete($record));
    }

    /**
     * @param  class-string  $resource
     * @param  class-string  $model
     */
    #[DataProvider('lookupResources')]
    public function test_an_admin_can_create_and_edit_a_lookup(string $resource, string $model): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        $record = $model::factory()->create();

        $this->assertTrue($resource::canCreate());
        $this->assertTrue($resource::canEdit($record));
    }

    /**
     * @param  class-string  $resource
     * @param  class-string  $model
     */
    #[DataProvider('lookupResources')]
    public function test_an_admin_can_delete_an_unused_lookup(string $resource, string $model): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        $this->assertTrue($resource::canDelete($model::factory()->create()));
    }

    public function test_an_admin_cannot_delete_a_category_still_used_by_a_recipe(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        $recipe = Recipe::factory()->create();

        $this->assertFalse(CategoryResource::canDelete($recipe->category));
    }

    public function test_an_admin_cannot_delete_a_food_still_used_by_an_ingredient(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        $ingredient = Ingredient::factory()->create();

        $this->assertFalse(FoodResource::canDelete($ingredient->food));
    }

    public function test_an_admin_cannot_delete_a_unit_still_used_by_an_ingredient(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        $unit = Unit::factory()->create();
        Ingredient::factory()->create(['unit_id' => $unit->id]);

        $this->assertFalse(UnitResource::canDelete($unit->fresh()));
    }

    public function test_an_admin_cannot_delete_an_attribute_still_used_by_an_ingredient(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        $attribute = IngredientAttribute::factory()->create();
        Ingredient::factory()->create()->ingredientAttributes()->attach($attribute);

        $this->assertFalse(IngredientAttributeResource::canDelete($attribute->fresh()));
    }

    public function test_lookup_lists_stay_readable_for_non_admins(): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]));

        $this->assertTrue(CategoryResource::canViewAny());
        $this->assertTrue(FoodResource::canViewAny());
        $this->assertTrue(UnitResource::canViewAny());
        $this->assertTrue(IngredientAttributeResource::canViewAny());
    }
}
