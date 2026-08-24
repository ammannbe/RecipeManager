<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\IngredientAttribute;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\RecipeImporter;
use App\Services\RecipeImport\RecipeJsonExporter;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipeImport\RecipeJsonValidator;
use Tests\TestCase;

class RecipeJsonExportTest extends TestCase
{
    private function seedLookups(): void
    {
        Category::factory()->create(['name' => 'Dessert']);
        Unit::factory()->create(['name' => 'g', 'name_shortcut' => 'g']);
        Unit::factory()->create(['name' => 'Prise', 'name_shortcut' => null]);

        foreach (['Mehl', 'Butter', 'Margarine', 'Apfel', 'Salz'] as $food) {
            Food::factory()->create(['name' => $food]);
        }

        foreach (['gesiebt', 'weich', 'säuerlich', 'geschält'] as $attribute) {
            IngredientAttribute::factory()->create(['name' => $attribute]);
        }

        foreach (['Kuchen', 'Herbst'] as $tag) {
            Tag::factory()->create(['name' => $tag]);
        }
    }

    public function test_an_exported_recipe_can_be_imported_again(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();
        Cookbook::factory()->create(['name' => 'Omas Backbuch', 'author_id' => $user->author_id]);

        $json = (string) json_encode(RecipeJsonSchema::example());
        $original = app(RecipeImporter::class)->import(
            app(RecipeJsonValidator::class)->validate($json),
            $user
        );

        $exported = app(RecipeJsonExporter::class)->toJson($original);

        $decoded = json_decode($exported, true);
        $decoded['name'] = 'Apfelkuchen 2';

        $copy = app(RecipeImporter::class)->import(
            app(RecipeJsonValidator::class)->validate((string) json_encode($decoded)),
            $user
        );

        $this->assertSame('Apfelkuchen 2', $copy->name);
        $this->assertSame($original->category_id, $copy->category_id);
        $this->assertSame($original->cookbook_id, $copy->cookbook_id);
        $this->assertSame($original->servings, $copy->servings);
        $this->assertEqualsCanonicalizing(
            $original->tags->pluck('name')->all(),
            $copy->tags->pluck('name')->all()
        );
        $this->assertSame(
            $original->ingredients()->count(),
            $copy->ingredients()->count()
        );
        $this->assertSame(
            $original->ingredientGroups()->pluck('name')->all(),
            $copy->ingredientGroups()->pluck('name')->all()
        );
    }

    public function test_the_export_omits_photos_by_default(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();
        Cookbook::factory()->create(['name' => 'Omas Backbuch', 'author_id' => $user->author_id]);

        $recipe = app(RecipeImporter::class)->import(
            app(RecipeJsonValidator::class)->validate((string) json_encode(RecipeJsonSchema::example())),
            $user
        );

        $data = app(RecipeJsonExporter::class)->toArray($recipe);

        $this->assertSame([], $data['photos']);
        $this->assertSame('apfelkuchen.json', app(RecipeJsonExporter::class)->filename($recipe));
    }
}
