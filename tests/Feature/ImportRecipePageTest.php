<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportRecipe;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Mail\LookupRequestedMail;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\IngredientAttribute;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\LookupType;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipeImport\ResolutionReport;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
     * @return array{key: string, type: string, value: string, canCreate: bool, createsRequest: bool, canOmit: bool, suggestions: array<int, array{id: int, name: string, score: int}>, context: array<int, string>, preselectedId: int|null}
     */
    private function unresolvedValue(Testable $component, string $value): array
    {
        /** @var array<int, array{key: string, type: string, value: string, canCreate: bool, createsRequest: bool, canOmit: bool, suggestions: array<int, array{id: int, name: string, score: int}>, context: array<int, string>, preselectedId: int|null}> $unresolved */
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

    public function test_it_is_not_in_the_navigation_but_linked_from_the_recipe_list(): void
    {
        $this->assertFalse(ImportRecipe::shouldRegisterNavigation());

        $this->actingAs(User::factory()->create(['admin' => false]));

        $this->get(RecipeResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee(ImportRecipe::getUrl(), false);
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

    public function test_the_confirmation_action_refuses_a_duplicate_name(): void
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
            ->assertHasErrors(['data.name' => __('A recipe named ":name" already exists in this cookbook.', ['name' => 'Apfelkuchen'])]);

        $this->assertSame(1, Recipe::query()->count());
    }

    public function test_a_duplicate_is_shown_on_the_resolve_step_with_a_free_name(): void
    {
        $this->seedLookups();
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => null, 'author_id' => $user->author_id]);

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->goToNextWizardStep()
            ->assertHasNoFormErrors()
            ->assertWizardCurrentStep(2)
            ->assertSet('data.name', 'Apfelkuchen')
            ->assertSee(__('A recipe named ":name" already exists in this cookbook.', ['name' => 'Apfelkuchen']))
            ->assertSee(__('Choose another name, for example ":name".', ['name' => 'Apfelkuchen (2)']))
            ->assertActionHidden(TestAction::make('forceDeleteTrashed')->schemaComponent('nameConflict'))
            ->goToWizardStep(3)
            ->assertHasFormErrors(['name'])
            ->assertWizardCurrentStep(2)
            ->callAction(TestAction::make('useAvailableName')->schemaComponent('nameConflict'))
            ->assertSet('data.name', 'Apfelkuchen (2)')
            ->assertDontSee(__('Choose another name, for example ":name".', ['name' => 'Apfelkuchen (2)']))
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(Recipe::class, ['name' => 'Apfelkuchen (2)', 'author_id' => $user->author_id]);
    }

    public function test_the_edited_name_is_imported(): void
    {
        $this->seedLookups();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->goToNextWizardStep()
            ->fillForm(['name' => 'Omas Apfelkuchen'])
            ->goToWizardStep(3)
            ->assertHasNoFormErrors()
            ->assertWizardCurrentStep(3)
            ->assertSee('Omas Apfelkuchen')
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(Recipe::class, ['name' => 'Omas Apfelkuchen']);
        $this->assertDatabaseMissing(Recipe::class, ['name' => 'Apfelkuchen']);
    }

    public function test_the_name_is_checked_again_when_the_cookbook_changes(): void
    {
        $this->seedLookups();
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $cookbook = Cookbook::factory()->create(['author_id' => $user->author_id]);
        Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => $cookbook->id, 'author_id' => $user->author_id]);
        $message = __('Choose another name, for example ":name".', ['name' => 'Apfelkuchen (2)']);

        Livewire::withQueryParams(['draft' => $this->draft($user)->id])
            ->test(ImportRecipe::class)
            ->assertWizardCurrentStep(2)
            ->assertDontSee($message)
            ->fillForm(['cookbook_id' => $cookbook->id])
            ->assertSee($message)
            ->call('import')
            ->assertHasErrors(['data.name'])
            ->fillForm(['cookbook_id' => null])
            ->assertDontSee($message)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(Recipe::class, ['name' => 'Apfelkuchen', 'cookbook_id' => null]);
    }

    public function test_a_duplicate_in_the_trash_can_be_deleted_permanently(): void
    {
        Storage::fake('recipes');
        $this->seedLookups();
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $trashed = Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => null, 'author_id' => $user->author_id]);
        Storage::disk('recipes')->put($trashed->id.'/photo.jpg', 'binary');
        $trashed->delete();

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->goToNextWizardStep()
            ->assertSee(__('A recipe named ":name" already exists in this cookbook (in the trash).', ['name' => 'Apfelkuchen']))
            ->callAction(TestAction::make('forceDeleteTrashed')->schemaComponent('nameConflict'))
            ->assertNotified(__('The recipe in the trash was deleted permanently.'))
            ->assertDontSee(__('A recipe named ":name" already exists in this cookbook (in the trash).', ['name' => 'Apfelkuchen']))
            ->call('import')
            ->assertHasNoErrors();

        $this->assertModelMissing($trashed);
        Storage::disk('recipes')->assertMissing($trashed->id.'/photo.jpg');
        $this->assertDatabaseHas(Recipe::class, ['name' => 'Apfelkuchen', 'deleted_at' => null]);
    }

    public function test_a_duplicate_in_the_trash_can_be_renamed(): void
    {
        $this->seedLookups();
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $trashed = Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => null, 'author_id' => $user->author_id]);
        $trashed->delete();
        Recipe::factory()->create(['name' => 'Apfelkuchen alt', 'cookbook_id' => null, 'author_id' => $user->author_id]);

        Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->goToNextWizardStep()
            ->mountAction(TestAction::make('renameTrashed')->schemaComponent('nameConflict'))
            ->assertSchemaStateSet(['name' => 'Apfelkuchen (2)'])
            ->fillForm(['name' => 'Apfelkuchen alt'])
            ->callMountedAction()
            ->assertHasActionErrors(['name'])
            ->fillForm(['name' => 'Apfelkuchen aus dem Papierkorb'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified(__('The recipe in the trash was renamed.'))
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame('Apfelkuchen aus dem Papierkorb', $trashed->fresh()?->name);
        $this->assertSoftDeleted($trashed);
        $this->assertDatabaseHas(Recipe::class, ['name' => 'Apfelkuchen', 'deleted_at' => null]);
    }

    public function test_a_duplicate_in_the_trash_the_user_may_not_change_only_offers_another_name(): void
    {
        $this->seedLookups();
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $cookbook = Cookbook::factory()->create();
        $cookbook->members()->attach($user->id, ['can_read' => true]);
        $trashed = Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => $cookbook->id, 'author_id' => $cookbook->author_id]);
        $trashed->delete();

        Livewire::withQueryParams(['draft' => $this->draft($user)->id])
            ->test(ImportRecipe::class)
            ->set('data.cookbook_id', $cookbook->id)
            ->assertSee(__('That recipe is in the trash and you may not change it. Choose another name, for example ":name".', ['name' => 'Apfelkuchen (2)']))
            ->assertActionHidden(TestAction::make('forceDeleteTrashed')->schemaComponent('nameConflict'))
            ->assertActionHidden(TestAction::make('renameTrashed')->schemaComponent('nameConflict'))
            ->assertActionVisible(TestAction::make('useAvailableName')->schemaComponent('nameConflict'));

        $this->assertSoftDeleted($trashed);
    }

    public function test_a_failed_import_leaves_no_requested_lookups_and_sends_no_mail(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();
        Mail::fake();
        User::factory()->admin()->create();
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->goToNextWizardStep();
        $item = $this->unresolvedValue($component, 'Mehl');

        // Someone saves the same name while the import runs: the importer's own check refuses it.
        Food::created(fn () => Recipe::factory()->create(['name' => 'Apfelkuchen', 'cookbook_id' => null, 'author_id' => $user->author_id]));

        $component
            ->set('data.decisions.'.md5($item['key']).'.create', true)
            ->call('import')
            ->assertHasErrors('data.json')
            ->assertNotified(
                Notification::make()
                    ->danger()
                    ->title(__('Recipe could not be imported'))
                    ->body(__('A recipe named ":name" already exists in this cookbook.', ['name' => 'Apfelkuchen']))
                    ->persistent()
            );

        $this->assertDatabaseMissing('foods', ['name' => 'Mehl']);
        $this->assertSame(0, Recipe::query()->withTrashed()->count());
        Mail::assertNothingSent();
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

    public function test_a_non_admin_requests_a_missing_food_and_can_use_it_right_away(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();

        Mail::fake();
        $admin = User::factory()->admin()->create(['locale' => 'en']);
        User::factory()->admin()->create();
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->call('import')
            ->assertHasErrors();

        $item = $this->unresolvedValue($component, 'Mehl');
        $this->assertTrue($item['canCreate']);
        $this->assertTrue($item['createsRequest']);

        $component
            ->set('data.decisions.'.md5($item['key']).'.create', true)
            ->call('import')
            ->assertHasNoErrors();

        $mehl = Food::query()->where('name', 'Mehl')->firstOrFail();
        $this->assertSame($user->id, $mehl->requested_by);

        // Every admin gets a mail, in their language.
        Mail::assertSent(LookupRequestedMail::class, 2);
        Mail::assertSent(LookupRequestedMail::class, fn (LookupRequestedMail $mail): bool => $mail->hasTo($admin->email)
            && $mail->locale === 'en' && $mail->requests === [['type' => 'food', 'value' => 'Mehl']]
            && str_contains($mail->render(), '/admin/foods'));
        $this->assertNotNull($mehl->requested_at);
        $this->assertSame(1, Recipe::query()->firstOrFail()->ingredients()->where('food_id', $mehl->id)->count());

        // Hidden from others until approved, offered to the requester.
        $other = User::factory()->create(['admin' => false]);
        $this->assertFalse(Food::query()->visibleTo($other)->whereKey($mehl->id)->exists());
        $this->assertTrue(Food::query()->visibleTo($user)->whereKey($mehl->id)->exists());
        $this->assertTrue(Food::query()->visibleTo(User::factory()->admin()->create())->whereKey($mehl->id)->exists());
    }

    public function test_a_food_requested_by_someone_else_is_reused(): void
    {
        $this->seedLookups();
        Food::query()->where('name', 'Mehl')->forceDelete();
        $first = User::factory()->create(['admin' => false]);
        $pending = Food::query()->forceCreate(['name' => 'Mehl', 'requested_by' => $first->id, 'requested_at' => now()]);

        $this->actingAs(User::factory()->create(['admin' => false]));

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload()])
            ->call('import');

        // Not offered, but requesting the same name takes the pending entry.
        $item = $this->unresolvedValue($component, 'Mehl');
        $this->assertSame([], $item['suggestions']);
        $component->set('data.decisions.'.md5($item['key']).'.create', true)->call('import')->assertHasNoErrors();

        $this->assertSame(1, Food::query()->where('name', 'Mehl')->count());
        $this->assertSame(1, Recipe::query()->firstOrFail()->ingredients()->where('food_id', $pending->id)->count());
    }

    public function test_an_omitted_food_drops_its_ingredient_and_the_units_only_it_used(): void
    {
        $this->seedLookups();
        $this->actingAs(User::factory()->create(['admin' => false]));

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload([
                'ingredients' => [
                    ['amount' => 1, 'unit' => 'Handvoll', 'food' => 'Zitronenmelisse', 'attributes' => ['frisch gezupft']],
                    ['amount' => 200, 'unit' => 'g', 'food' => 'Mehl', 'attributes' => []],
                ],
                'ingredient_groups' => [[
                    'name' => 'Deko',
                    'ingredients' => [['amount' => 1, 'unit' => null, 'food' => 'Zitronenmelisse', 'attributes' => []]],
                ]],
            ])])
            ->call('import')
            ->assertHasErrors();

        $food = $this->unresolvedValue($component, 'Zitronenmelisse');
        $this->assertTrue($food['canOmit']);

        $component
            ->set('data.decisions.'.md5($food['key']).'.omit', true)
            ->call('import')
            ->assertHasNoErrors();

        $recipe = Recipe::query()->firstOrFail();
        $this->assertSame(['Mehl'], $recipe->ingredients()->with('food')->get()->pluck('food.name')->all());
        // The group would be empty and is left out; unit and attribute were only used there.
        $this->assertSame(0, $recipe->ingredientGroups()->count());
        $this->assertDatabaseMissing('foods', ['name' => 'Zitronenmelisse']);
        $this->assertDatabaseMissing('units', ['name' => 'Handvoll']);
    }

    public function test_omitting_every_ingredient_is_refused(): void
    {
        $this->seedLookups();
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload([
                'ingredients' => [['amount' => 1, 'unit' => null, 'food' => 'Zitronenmelisse', 'attributes' => []]],
                'ingredient_groups' => [],
            ])])
            ->call('import');

        $item = $this->unresolvedValue($component, 'Zitronenmelisse');
        $component->set('data.decisions.'.md5($item['key']).'.omit', true)->call('import')->assertHasErrors();

        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_a_category_cannot_be_omitted(): void
    {
        $this->seedLookups();
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => $this->payload(['category' => 'Unbekannt'])])
            ->call('import');

        $item = $this->unresolvedValue($component, 'Unbekannt');
        $this->assertFalse($item['canOmit']);
        $component->set('data.decisions.'.md5($item['key']).'.omit', true)->call('import')->assertHasErrors();

        $this->assertDatabaseCount('recipes', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function draft(User $user, array $overrides = []): RecipeDraft
    {
        return RecipeDraft::query()->create([
            'user_id' => $user->id,
            'data' => array_merge(RecipeJsonSchema::example(), ['cookbook' => null], $overrides),
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_a_draft_starts_at_the_resolve_step_without_creating_anything(): void
    {
        $this->seedLookups();
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $draft = $this->draft($user, ['category' => 'Rezeptideen']);
        $categories = Category::query()->count();

        /** @var Testable<ImportRecipe> $component */
        $component = Livewire::withQueryParams(['draft' => $draft->id])
            ->test(ImportRecipe::class)
            ->assertSet('draftId', $draft->id)
            ->assertWizardCurrentStep(2)
            ->assertFormFieldExists('cookbook_id')
            ->assertFormFieldExists('is_public');

        $this->assertSame('Rezeptideen', $this->unresolvedValue($component, 'Rezeptideen')['value']);
        $this->assertSame($categories, Category::query()->count());
        $this->assertModelExists($draft);
    }

    public function test_importing_a_draft_uses_cookbook_and_visibility_and_removes_the_draft(): void
    {
        $this->seedLookups();
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        $cookbook = Cookbook::factory()->create(['author_id' => $user->author_id, 'is_public' => false]);
        $draft = $this->draft($user);

        Livewire::withQueryParams(['draft' => $draft->id])
            ->test(ImportRecipe::class)
            ->fillForm(['cookbook_id' => $cookbook->id, 'is_public' => false])
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas(Recipe::class, ['name' => 'Apfelkuchen', 'cookbook_id' => $cookbook->id, 'is_public' => false]);
        $this->assertModelMissing($draft);
    }

    public function test_foreign_or_expired_drafts_are_not_loaded(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);
        $foreign = $this->draft(User::factory()->create());
        $expired = $this->draft($user);
        $expired->update(['expires_at' => now()->subMinute()]);

        foreach ([$foreign, $expired] as $draft) {
            Livewire::withQueryParams(['draft' => $draft->id])
                ->test(ImportRecipe::class)
                ->assertSet('draftId', null)
                ->assertSet('data.json', null)
                ->assertWizardCurrentStep(1)
                ->assertNotified(__('The recipe draft was not found or has expired.'));
        }
    }
}
