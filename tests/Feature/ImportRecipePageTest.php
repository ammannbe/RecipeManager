<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportRecipe;
use App\Models\Category;
use App\Models\Food;
use App\Models\IngredientAttribute;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\LookupType;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipeImport\ResolutionReport;
use App\Services\RecipeImport\UnresolvedValue;
use Filament\Notifications\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ImportRecipePageTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): string
    {
        return (string) json_encode(array_merge(
            RecipeJsonSchema::example(),
            ['cookbook' => null],
            $overrides
        ));
    }

    /**
     * @param  Testable<ImportRecipe>  $component
     * @return array{key: string, type: string, value: string, canCreate: bool, suggestions: array<int, array{id: int, name: string, score: int}>, context: array<int, string>, preselectedId: int|null}
     */
    private function unresolvedValue(Testable $component, string $value): array
    {
        /** @var array<int, array{key: string, type: string, value: string, canCreate: bool, suggestions: array<int, array{id: int, name: string, score: int}>, context: array<int, string>, preselectedId: int|null}> $unresolved */
        $unresolved = $component->get('unresolved');

        foreach ($unresolved as $item) {
            if ($item['value'] === $value) {
                return $item;
            }
        }

        $this->fail("No unresolved value \"{$value}\".");
    }

    public function test_guests_are_redirected_to_the_login(): void
    {
        $this->get(ImportRecipe::getUrl())->assertRedirect('/admin/login');
    }

    public function test_it_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]));

        $this->get(ImportRecipe::getUrl())->assertSuccessful();
    }

    public function test_the_submit_button_is_rendered_as_markup_not_escaped_html(): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]));

        $this->get(ImportRecipe::getUrl())
            ->assertSuccessful()
            ->assertDontSee('&lt;button', escape: false);
    }

    public function test_it_imports_a_recipe_that_needs_no_resolution(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(Recipe::class, [
            'name' => 'Apfelkuchen',
            'author_id' => $user->author_id,
        ]);
    }

    public function test_the_confirmation_action_imports_the_recipe(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->callAction('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(Recipe::class, [
            'name' => 'Apfelkuchen',
            'author_id' => $user->author_id,
        ]);
    }

    public function test_the_confirmation_action_shows_duplicate_errors(): void
    {
        $this->seedLookups();

        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->callAction('import')
            ->assertHasNoErrors();

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->callAction('import')
            ->assertHasErrors('data.json')
            ->assertNotified(
                Notification::make()
                    ->danger()
                    ->title(__('Recipe could not be imported'))
                    ->body(__('A recipe named ":name" already exists in this cookbook.', [
                        'name' => 'Apfelkuchen',
                    ]))
                    ->persistent()
            );
    }

    public function test_it_strips_a_markdown_code_fence(): void
    {
        $this->seedLookups();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => "```json\n".$this->payload()."\n```"])
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(Recipe::class, ['name' => 'Apfelkuchen']);
    }

    public function test_it_reports_invalid_json(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => '{nope'])
            ->call('import')
            ->assertHasErrors('data.json');

        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_it_lists_unresolved_values_after_parsing(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();

        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->call('import')
            ->assertHasErrors();

        $this->assertSame('Mehl', $this->unresolvedValue($component, 'Mehl')['value']);
        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_a_confident_suggestion_is_preselected(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();
        $vanille = Food::factory()->create(['name' => 'Vanillezucker']);

        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload([
                'ingredients' => [['amount' => 1, 'unit' => null, 'food' => 'Vanillinzucker']],
                'ingredient_groups' => [],
            ])])
            ->call('import');

        $item = $this->unresolvedValue($component, 'Vanillinzucker');

        $this->assertSame($vanille->id, $item['preselectedId']);
        $this->assertSame(
            $vanille->id,
            $component->get('data.decisions.'.md5($item['key']).'.id')
        );
    }

    public function test_a_preselected_suggestion_is_used_on_import(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();
        $vanille = Food::factory()->create(['name' => 'Vanillezucker']);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload([
                'ingredients' => [['amount' => 1, 'unit' => null, 'food' => 'Vanillinzucker']],
                'ingredient_groups' => [],
            ])])
            ->call('import')
            ->assertHasNoErrors();

        $recipe = Recipe::query()->where('name', 'Apfelkuchen')->firstOrFail();

        $this->assertSame(1, $recipe->ingredients()->where('food_id', $vanille->id)->count());
        $this->assertDatabaseMissing('foods', ['name' => 'Vanillinzucker']);
    }

    public function test_a_weak_suggestion_is_not_preselected(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();
        Food::factory()->create(['name' => 'Mehlschwitze']);

        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->call('import')
            ->assertHasErrors();

        $item = $this->unresolvedValue($component, 'Mehl');

        $this->assertNull($item['preselectedId']);
        $this->assertNull($component->get('data.decisions.'.md5($item['key']).'.id'));
    }

    public function test_a_preselection_does_not_override_a_user_choice(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();
        Food::factory()->create(['name' => 'Vanillezucker']);
        $chosen = Food::factory()->create(['name' => 'Rohrzucker']);

        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload([
                'ingredients' => [['amount' => 1, 'unit' => null, 'food' => 'Vanillinzucker']],
                'ingredient_groups' => [],
            ])]);

        $key = md5(ResolutionReport::key(LookupType::Food, 'Vanillinzucker'));

        $component
            ->set('data.decisions.'.$key.'.id', $chosen->id)
            ->call('import')
            ->assertHasNoErrors();

        $recipe = Recipe::query()->where('name', 'Apfelkuchen')->firstOrFail();

        $this->assertSame(1, $recipe->ingredients()->where('food_id', $chosen->id)->count());
    }

    public function test_every_unresolved_value_gets_a_bindable_decision_entry(): void
    {
        $this->seedLookups();

        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload([
                'ingredients' => [[
                    'amount' => 1,
                    'unit' => 'Handvoll',
                    'food' => 'Salz',
                    'attributes' => ['grob gemahlen'],
                ]],
                'ingredient_groups' => [],
            ])])
            ->call('import');

        foreach (['Handvoll', 'grob gemahlen'] as $value) {
            $item = $this->unresolvedValue($component, $value);
            $key = md5($item['key']);

            $this->assertNull($item['preselectedId']);
            $this->assertIsArray($component->get('data.decisions.'.$key));
            $this->assertFalse($component->get('data.decisions.'.$key.'.create'));
        }
    }

    public function test_an_unpreselected_unit_and_attribute_can_be_created(): void
    {
        $this->seedLookups();

        $this->actingAs(User::factory()->admin()->create());

        $unitKey = md5(ResolutionReport::key(LookupType::Unit, 'Handvoll'));
        $attributeKey = md5(ResolutionReport::key(LookupType::Attribute, 'grob gemahlen'));

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload([
                'ingredients' => [[
                    'amount' => 1,
                    'unit' => 'Handvoll',
                    'food' => 'Salz',
                    'attributes' => ['grob gemahlen'],
                ]],
                'ingredient_groups' => [],
            ])])
            ->set('data.decisions.'.$unitKey.'.create', true)
            ->set('data.decisions.'.$attributeKey.'.create', true)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('units', ['name' => 'Handvoll']);
        $this->assertDatabaseHas('ingredient_attributes', ['name' => 'grob gemahlen']);

        $recipe = Recipe::query()->where('name', 'Apfelkuchen')->firstOrFail();
        $ingredient = $recipe->ingredients()->firstOrFail();

        $this->assertSame('Handvoll', $ingredient->unit?->name);
        $this->assertSame(['grob gemahlen'], $ingredient->ingredientAttributes->pluck('name')->all());
    }

    public function test_an_unpreselected_unit_can_be_mapped_to_an_existing_one(): void
    {
        $this->seedLookups();

        $this->actingAs(User::factory()->admin()->create());

        $prise = Unit::query()->where('name', 'Prise')->firstOrFail();

        $unitKey = md5(ResolutionReport::key(LookupType::Unit, 'Handvoll'));

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload([
                'ingredients' => [[
                    'amount' => 1,
                    'unit' => 'Handvoll',
                    'food' => 'Salz',
                    'attributes' => [],
                ]],
                'ingredient_groups' => [],
            ])])
            ->set('data.decisions.'.$unitKey.'.id', $prise->id)
            ->call('import')
            ->assertHasNoErrors();

        $recipe = Recipe::query()->where('name', 'Apfelkuchen')->firstOrFail();

        $this->assertSame($prise->id, $recipe->ingredients()->firstOrFail()->unit_id);
    }

    public function test_an_unresolved_unit_and_attribute_may_simply_be_omitted(): void
    {
        $this->seedLookups();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload([
                'ingredients' => [[
                    'amount' => 1,
                    'unit' => 'Handvoll',
                    'food' => 'Salz',
                    'attributes' => ['grob gemahlen'],
                ]],
                'ingredient_groups' => [],
                'tags' => ['Unbekannt'],
            ])])
            ->call('import')
            ->assertHasNoErrors();

        $recipe = Recipe::query()->where('name', 'Apfelkuchen')->firstOrFail();
        $ingredient = $recipe->ingredients()->firstOrFail();

        $this->assertNull($ingredient->unit_id);
        $this->assertCount(0, $ingredient->ingredientAttributes);
        $this->assertCount(0, $recipe->tags);

        $this->assertDatabaseMissing('units', ['name' => 'Handvoll']);
        $this->assertDatabaseMissing('ingredient_attributes', ['name' => 'grob gemahlen']);
        $this->assertDatabaseMissing('tags', ['name' => 'Unbekannt']);
    }

    public function test_an_unresolved_food_still_blocks_the_import(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Salz')->forceDelete();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload([
                'ingredients' => [[
                    'amount' => 1,
                    'unit' => null,
                    'food' => 'Salz',
                    'attributes' => [],
                ]],
                'ingredient_groups' => [],
            ])])
            ->call('import')
            ->assertHasErrors();

        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_an_admin_can_create_a_missing_food_from_the_resolve_step(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();

        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->call('import')
            ->assertHasErrors();

        $item = $this->unresolvedValue($component, 'Mehl');

        $component
            ->set('data.decisions.'.md5($item['key']).'.create', true)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('foods', ['name' => 'Mehl']);
        $this->assertDatabaseHas(Recipe::class, ['name' => 'Apfelkuchen']);
    }

    public function test_an_admin_can_map_a_missing_food_to_an_existing_one(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();
        $dinkel = Food::factory()->create(['name' => 'Dinkelmehl']);

        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->call('import')
            ->assertHasErrors();

        $item = $this->unresolvedValue($component, 'Mehl');

        $component
            ->set('data.decisions.'.md5($item['key']).'.id', $dinkel->id)
            ->call('import')
            ->assertHasNoErrors();

        $recipe = Recipe::query()->where('name', 'Apfelkuchen')->firstOrFail();

        $this->assertSame(1, $recipe->ingredients()->where('food_id', $dinkel->id)->count());
        $this->assertDatabaseMissing('foods', ['name' => 'Mehl']);
    }

    public function test_a_non_admin_cannot_create_a_food_through_the_page(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();

        $user = User::factory()->create(['admin' => false]);

        $this->assertFalse(
            (new UnresolvedValue(LookupType::Food, 'Mehl', [], LookupType::Food->canBeCreatedBy($user)))->canCreate
        );

        $this->actingAs($user);

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->call('import')
            ->assertHasErrors();

        $item = $this->unresolvedValue($component, 'Mehl');

        $this->assertFalse($item['canCreate']);

        // Forging the toggle must not bypass the policy.
        $component
            ->set('data.decisions.'.md5($item['key']).'.create', true)
            ->call('import');

        $this->assertDatabaseMissing('foods', ['name' => 'Mehl']);
        $this->assertDatabaseCount('recipes', 0);
    }
}
