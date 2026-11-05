<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\RecipeImporter;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipeImport\RecipeJsonValidator;
use App\Services\RecipeNames;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecipeImportDuplicatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Category::factory()->create(['name' => 'Dessert']);
        Unit::factory()->create(['name' => 'g', 'name_shortcut' => 'g']);
        Food::factory()->create(['name' => 'Mehl']);
    }

    /**
     * Example recipe without tags and attributes, so only the seeded lookups are needed.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function import(User $user, array $overrides = [], ?int $cookbookId = null, bool $renameDuplicate = false): Recipe
    {
        $parsed = app(RecipeJsonValidator::class)->validate((string) json_encode(array_merge(RecipeJsonSchema::example(), [
            'cookbook' => null,
            'tags' => [],
            'ingredients' => [['amount' => 200, 'unit' => 'g', 'food' => 'Mehl', 'attributes' => []]],
            'ingredient_groups' => [],
        ], $overrides)));

        return app(RecipeImporter::class)->import($parsed, $user, [], cookbookId: $cookbookId, renameDuplicate: $renameDuplicate);
    }

    private function refused(callable $import): ValidationException
    {
        try {
            $import();
        } catch (ValidationException $exception) {
            return $exception;
        }

        $this->fail('The import was not refused.');
    }

    public function test_the_duplicate_check_uses_the_explicitly_chosen_cookbook(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $named = Cookbook::factory()->create(['author_id' => $user->author_id, 'name' => 'Omas Backbuch']);
        $chosen = Cookbook::factory()->create(['author_id' => $user->author_id]);
        Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => $chosen->id, 'author_id' => $user->author_id]);

        // The JSON names another cookbook, but the recipe would be saved in the chosen one.
        $this->refused(fn () => $this->import($user, ['cookbook' => 'Omas Backbuch'], $chosen->id));

        // And a duplicate only in the cookbook named in the JSON does not block the chosen one.
        Recipe::query()->where('cookbook_id', $chosen->id)->forceDelete();
        Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => $named->id, 'author_id' => $user->author_id]);

        $recipe = $this->import($user, ['cookbook' => 'Omas Backbuch'], $chosen->id);

        $this->assertSame($chosen->id, $recipe->cookbook_id);
    }

    public function test_without_cookbook_names_are_unique_per_author(): void
    {
        $user = User::factory()->create(['admin' => false]);
        Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => null]);

        $this->import($user);

        $exception = $this->refused(fn () => $this->import($user));

        $this->assertSame(
            __('A recipe named ":name" already exists in this cookbook.', ['name' => 'Apfelkuchen']),
            $exception->validator->errors()->first('json'),
        );
    }

    public function test_a_recipe_in_the_trash_counts_and_is_named_as_such(): void
    {
        $user = User::factory()->create(['admin' => false]);
        Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => null, 'author_id' => $user->author_id])->delete();

        $exception = $this->refused(fn () => $this->import($user));

        $this->assertSame(
            __('A recipe named ":name" already exists in this cookbook (in the trash).', ['name' => 'Apfelkuchen']),
            $exception->validator->errors()->first('json'),
        );
    }

    public function test_a_duplicate_can_be_renamed_to_a_free_name(): void
    {
        $user = User::factory()->create(['admin' => false]);

        foreach (['Apfelkuchen', 'Apfelkuchen (2)'] as $name) {
            Recipe::factory()->create(['name' => $name, 'cookbook_id' => null, 'author_id' => $user->author_id]);
        }

        Recipe::query()->where('name', 'Apfelkuchen (2)')->firstOrFail()->delete();

        $this->assertSame('Apfelkuchen (3)', $this->import($user, renameDuplicate: true)->name);
        $this->assertSame('Apfelkuchen (4)', app(RecipeNames::class)->availableName('Apfelkuchen (3)', null, $user->author_id));
    }

    public function test_a_free_name_stays_within_the_column_length(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $long = str_repeat('Ä', RecipeNames::MAX_LENGTH);
        Recipe::factory()->create(['name' => $long, 'cookbook_id' => null, 'author_id' => $user->author_id]);

        $name = app(RecipeNames::class)->availableName($long, null, $user->author_id);

        $this->assertSame(RecipeNames::MAX_LENGTH, mb_strlen($name));
        $this->assertStringEndsWith(' (2)', $name);
        $this->assertSame($long, app(RecipeNames::class)->availableName($long, null, User::factory()->create()->author_id));
    }
}
