<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientAttribute;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\LookupType;
use App\Services\RecipeImport\RecipeImporter;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipeImport\RecipeJsonValidator;
use App\Services\RecipeImport\RelationResolver;
use App\Services\RecipeImport\ResolutionReport;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecipeJsonImportTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): string
    {
        return (string) json_encode(array_merge(RecipeJsonSchema::example(), $overrides));
    }

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

    private function import(string $json, User $user): Recipe
    {
        $parsed = app(RecipeJsonValidator::class)->validate($json);

        return app(RecipeImporter::class)->import($parsed, $user);
    }

    public function test_it_imports_a_complete_recipe(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();
        Cookbook::factory()->create(['name' => 'Omas Backbuch', 'author_id' => $user->author_id]);

        $recipe = $this->import($this->payload(), $user);

        $this->assertSame('Apfelkuchen', $recipe->name);
        $this->assertSame('Dessert', $recipe->category->name);
        $this->assertSame('Omas Backbuch', $recipe->cookbook?->name);
        $this->assertSame(8.0, $recipe->servings);
        $this->assertSame('Stück', $recipe->serving_type);
        $this->assertSame('normal', $recipe->complexity->value);
        $this->assertSame('01:15', $recipe->preparation_time?->format('H:i'));
        $this->assertStringContainsString('<ol>', $recipe->instructions);
        $this->assertEqualsCanonicalizing(['Kuchen', 'Herbst'], $recipe->tags->pluck('name')->all());
        $this->assertSame($user->author_id, $recipe->author_id);
    }

    public function test_it_assigns_sequential_positions_per_group(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();
        Cookbook::factory()->create(['name' => 'Omas Backbuch', 'author_id' => $user->author_id]);

        $recipe = $this->import($this->payload(), $user);

        $this->assertSame([1], $recipe->ungroupedIngredients->pluck('position')->all());

        $groups = $recipe->ingredientGroups()->get();
        $this->assertSame(['Teig', 'Belag'], $groups->pluck('name')->all());
        $this->assertSame([1, 2], $groups->pluck('position')->all());

        $teig = $groups->firstWhere('name', 'Teig');
        $this->assertSame(
            [1, 2],
            $teig->ingredients->whereNull('ingredient_id')->pluck('position')->values()->all()
        );
    }

    public function test_it_imports_alternatives_as_child_ingredients(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();
        Cookbook::factory()->create(['name' => 'Omas Backbuch', 'author_id' => $user->author_id]);

        $recipe = $this->import($this->payload(), $user);

        $butter = Ingredient::query()
            ->where('recipe_id', $recipe->id)
            ->whereHas('food', fn ($query) => $query->where('name', 'Butter'))
            ->firstOrFail();

        $alternatives = $butter->ingredients;

        $this->assertCount(1, $alternatives);
        $this->assertSame('Margarine', $alternatives->first()->food->name);
        $this->assertSame($butter->ingredient_group_id, $alternatives->first()->ingredient_group_id);
        $this->assertSame($recipe->id, $alternatives->first()->recipe_id);
    }

    public function test_it_syncs_ingredient_attributes(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();
        Cookbook::factory()->create(['name' => 'Omas Backbuch', 'author_id' => $user->author_id]);

        $recipe = $this->import($this->payload(), $user);

        $apple = Ingredient::query()
            ->where('recipe_id', $recipe->id)
            ->whereHas('food', fn ($query) => $query->where('name', 'Apfel'))
            ->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['säuerlich', 'geschält'],
            $apple->ingredientAttributes->pluck('name')->all()
        );
    }

    public function test_it_rejects_invalid_json(): void
    {
        $this->expectException(ValidationException::class);

        app(RecipeJsonValidator::class)->validate('{not json');
    }

    public function test_it_rejects_a_list_of_recipes(): void
    {
        $this->expectException(ValidationException::class);

        app(RecipeJsonValidator::class)->validate('[{"name":"A"}]');
    }

    public function test_it_rejects_an_unknown_complexity(): void
    {
        $this->expectException(ValidationException::class);

        app(RecipeJsonValidator::class)->validate($this->payload(['complexity' => 'trivial']));
    }

    public function test_it_rejects_a_missing_food(): void
    {
        $this->expectException(ValidationException::class);

        app(RecipeJsonValidator::class)->validate($this->payload([
            'ingredients' => [['amount' => 1, 'unit' => null, 'food' => '']],
        ]));
    }

    public function test_nothing_is_persisted_when_a_lookup_is_unresolved(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();

        $user = User::factory()->admin()->create();
        Cookbook::factory()->create(['name' => 'Omas Backbuch', 'author_id' => $user->author_id]);

        try {
            $this->import($this->payload(), $user);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseCount('ingredients', 0);
    }

    public function test_admins_may_create_missing_lookups(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();

        $user = User::factory()->admin()->create();
        Cookbook::factory()->create(['name' => 'Omas Backbuch', 'author_id' => $user->author_id]);

        $parsed = app(RecipeJsonValidator::class)->validate($this->payload());
        $resolver = app(RelationResolver::class);
        $report = $resolver->resolve($parsed, $user);

        $unresolved = collect($report->unresolved)->firstWhere('value', 'Mehl');
        $this->assertNotNull($unresolved);
        $this->assertTrue($unresolved->canCreate);

        $id = $resolver->create(LookupType::Food, 'Mehl', $user);

        $recipe = app(RecipeImporter::class)->import($parsed, $user, [$unresolved->key() => $id]);

        $this->assertDatabaseHas('foods', ['id' => $id, 'name' => 'Mehl']);
        $this->assertSame(1, $recipe->ingredients()->where('food_id', $id)->count());
    }

    public function test_non_admins_may_not_create_foods(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();

        $user = User::factory()->create(['admin' => false]);

        $parsed = app(RecipeJsonValidator::class)->validate($this->payload(['cookbook' => null]));
        $report = app(RelationResolver::class)->resolve($parsed, $user);

        $unresolved = collect($report->unresolved)->firstWhere('value', 'Mehl');

        $this->assertNotNull($unresolved);
        $this->assertFalse($unresolved->canCreate);
    }

    public function test_non_admins_may_create_a_cookbook(): void
    {
        $user = User::factory()->create(['admin' => false]);

        $resolver = app(RelationResolver::class);
        $id = $resolver->create(LookupType::Cookbook, 'Mein Buch', $user);

        $this->assertDatabaseHas('cookbooks', [
            'id' => $id,
            'name' => 'Mein Buch',
            'author_id' => $user->author_id,
        ]);
    }

    public function test_it_suggests_similar_names(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();

        $parsed = app(RecipeJsonValidator::class)->validate($this->payload([
            'cookbook' => null,
            'ingredient_groups' => [[
                'name' => 'Teig',
                'ingredients' => [['amount' => 1, 'unit' => 'g', 'food' => 'Äpfel']],
            ]],
            'ingredients' => [],
        ]));

        $report = app(RelationResolver::class)->resolve($parsed, $user);
        $unresolved = collect($report->unresolved)->firstWhere('value', 'Äpfel');

        $this->assertNotNull($unresolved);
        $this->assertContains('Apfel', array_column($unresolved->suggestions, 'name'));
    }

    public function test_it_matches_a_plural_food_to_its_singular(): void
    {
        $this->seedLookups();
        $ei = Food::factory()->create(['name' => 'Ei']);

        $user = User::factory()->admin()->create();

        $parsed = app(RecipeJsonValidator::class)->validate($this->payload([
            'cookbook' => null,
            'ingredients' => [['amount' => 3, 'unit' => null, 'food' => 'Eier']],
            'ingredient_groups' => [],
        ]));

        $report = app(RelationResolver::class)->resolve($parsed, $user);

        $this->assertSame(
            $ei->id,
            $report->resolved[ResolutionReport::key(LookupType::Food, 'Eier')] ?? null
        );
    }

    public function test_it_does_not_fold_a_plural_when_several_rows_match(): void
    {
        $this->seedLookups();
        Food::factory()->create(['name' => 'Ei']);
        Food::factory()->create(['name' => 'Eis']);

        $user = User::factory()->admin()->create();

        $parsed = app(RecipeJsonValidator::class)->validate($this->payload([
            'cookbook' => null,
            'ingredients' => [['amount' => 3, 'unit' => null, 'food' => 'Eie']],
            'ingredient_groups' => [],
        ]));

        $report = app(RelationResolver::class)->resolve($parsed, $user);

        $this->assertArrayNotHasKey(
            ResolutionReport::key(LookupType::Food, 'Eie'),
            $report->resolved
        );
    }

    public function test_it_matches_a_unit_echoed_with_its_shortcut_in_brackets(): void
    {
        $this->seedLookups();
        Unit::query()->where('name', 'g')->update(['name' => 'Gramm', 'name_shortcut' => 'g']);

        $user = User::factory()->admin()->create();

        $parsed = app(RecipeJsonValidator::class)->validate($this->payload([
            'cookbook' => null,
            'ingredients' => [['amount' => 1, 'unit' => 'Gramm (g)', 'food' => 'Salz']],
            'ingredient_groups' => [],
        ]));

        $report = app(RelationResolver::class)->resolve($parsed, $user);

        $this->assertArrayHasKey(
            ResolutionReport::key(LookupType::Unit, 'Gramm (g)'),
            $report->resolved
        );
    }

    public function test_it_reports_which_ingredients_an_unresolved_attribute_belongs_to(): void
    {
        $this->seedLookups();
        IngredientAttribute::query()->where('name', 'gesiebt')->forceDelete();

        $user = User::factory()->admin()->create();

        $parsed = app(RecipeJsonValidator::class)->validate($this->payload(['cookbook' => null]));
        $report = app(RelationResolver::class)->resolve($parsed, $user);

        $unresolved = collect($report->unresolved)->firstWhere('value', 'gesiebt');

        $this->assertNotNull($unresolved);
        $this->assertSame(['Mehl'], $unresolved->context);
    }

    public function test_it_matches_a_unit_by_its_shortcut(): void
    {
        $this->seedLookups();
        Unit::query()->where('name', 'g')->update(['name' => 'Gramm', 'name_shortcut' => 'g']);

        $user = User::factory()->admin()->create();

        $parsed = app(RecipeJsonValidator::class)->validate($this->payload(['cookbook' => null]));
        $report = app(RelationResolver::class)->resolve($parsed, $user);

        $this->assertArrayHasKey(
            ResolutionReport::key(LookupType::Unit, 'g'),
            $report->resolved
        );
    }

    public function test_it_restores_a_trashed_lookup_instead_of_duplicating_it(): void
    {
        $this->seedLookups();

        $food = Food::query()->where('name', 'Mehl')->firstOrFail();
        $food->delete();

        $user = User::factory()->admin()->create();

        $parsed = app(RecipeJsonValidator::class)->validate($this->payload(['cookbook' => null]));
        $report = app(RelationResolver::class)->resolve($parsed, $user);

        $this->assertSame($food->id, $report->resolved[ResolutionReport::key(LookupType::Food, 'Mehl')]);
        $this->assertDatabaseCount('foods', 5);
        $this->assertNull(Food::query()->findOrFail($food->id)->deleted_at);
    }

    public function test_a_non_admin_cannot_override_the_author(): void
    {
        $this->seedLookups();

        $user = User::factory()->create(['admin' => false]);
        $other = Author::factory()->create(['name' => 'Somebody else']);

        $recipe = $this->import($this->payload([
            'cookbook' => null,
            'author' => $other->name,
        ]), $user);

        $this->assertSame($user->author_id, $recipe->author_id);
    }

    public function test_it_rejects_a_duplicate_recipe_in_the_same_cookbook(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();
        Cookbook::factory()->create(['name' => 'Omas Backbuch', 'author_id' => $user->author_id]);

        $this->import($this->payload(), $user);

        $this->expectException(ValidationException::class);

        $this->import($this->payload(), $user);
    }
}
