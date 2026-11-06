<?php

namespace Tests\Feature;

use App\Enums\Measure;
use App\Filament\Pages\ImportRecipe;
use App\Filament\Resources\Foods\Pages\EditFood;
use App\Mail\LookupRequestedMail;
use App\Models\Category;
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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Resolve step of the import wizard: names and conversion data of new foods, units, attributes and tags.
 */
class ImportNewLookupsTest extends TestCase
{
    private Unit $piece;

    protected function setUp(): void
    {
        parent::setUp();

        Category::factory()->create(['name' => 'Dessert']);
        Unit::factory()->create(['name' => 'g', 'name_shortcut' => 'g', 'name_plural' => null, 'name_plural_shortcut' => null, 'measure' => Measure::Mass, 'base_amount' => 1]);
        Unit::factory()->create(['name' => 'Prise', 'name_shortcut' => null, 'name_plural' => null, 'name_plural_shortcut' => null]);
        $this->piece = Unit::factory()->create(['name' => 'Stück', 'name_shortcut' => null, 'name_plural' => null, 'name_plural_shortcut' => null]);

        // Mehl and Apfel are new.
        foreach (['Butter', 'Margarine', 'Salz'] as $food) {
            Food::factory()->create(['name' => $food]);
        }

        foreach (['gesiebt', 'weich', 'säuerlich', 'geschält'] as $attribute) {
            IngredientAttribute::factory()->create(['name' => $attribute]);
        }

        Tag::factory()->create(['name' => 'Kuchen']);
    }

    /**
     * @return Testable<ImportRecipe>
     */
    private function resolveStep(): Testable
    {
        return Livewire::test(ImportRecipe::class)
            ->fillForm(['json' => (string) json_encode([...RecipeJsonSchema::example(), 'cookbook' => null])])
            ->goToNextWizardStep()
            ->assertHasNoFormErrors()
            // A new request builds the resolve fields for the values just parsed.
            ->call('$refresh');
    }

    private function path(LookupType $type, string $value): string
    {
        return 'data.decisions.'.md5(ResolutionReport::key($type, $value));
    }

    /**
     * The field path relative to the form's state path ("data").
     */
    private function formPath(LookupType $type, string $value): string
    {
        return 'decisions.'.md5(ResolutionReport::key($type, $value));
    }

    /**
     * @param  Testable<ImportRecipe>  $component
     * @return Testable<ImportRecipe>
     */
    private function createAll(Testable $component): Testable
    {
        return $component
            ->set($this->path(LookupType::Food, 'Mehl').'.create', true)
            ->set($this->path(LookupType::Food, 'Apfel').'.create', true)
            ->set($this->path(LookupType::Tag, 'Herbst').'.create', true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function answer(array $data): array
    {
        return ['model' => 'test', 'usage' => ['input_tokens' => 50, 'output_tokens' => 30], 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
    }

    private function enableAi(int $limit = 5): void
    {
        config([
            'services.openai.key' => 'test-key',
            'services.openai.url' => 'https://openai.test/v1',
            'services.openai.hourly_limit' => $limit,
        ]);
    }

    public function test_the_name_of_an_entry_to_create_is_editable(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->createAll($this->resolveStep())
            ->assertFormFieldVisible($this->formPath(LookupType::Food, 'Mehl').'.name')
            ->assertFormFieldEnabled($this->formPath(LookupType::Food, 'Mehl').'.name')
            ->assertSet($this->path(LookupType::Food, 'Mehl').'.name', 'Mehl');
    }

    public function test_the_attributes_of_a_new_food_can_be_corrected_in_the_wizard(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $attributes = $this->formPath(LookupType::Food, 'Mehl').'.attributes';

        $component = $this->resolveStep()
            ->assertFormFieldHidden($attributes)
            ->set($this->path(LookupType::Food, 'Mehl').'.create', true)
            ->assertFormFieldVisible($attributes)
            ->assertSet($this->path(LookupType::Food, 'Mehl').'.attributes', ['gesiebt'])
            ->set($this->path(LookupType::Food, 'Mehl').'.attributes', ['weich', 'fein'])
            ->assertHasNoErrors([$this->path(LookupType::Food, 'Mehl').'.attributes']);

        // The JSON carries the new attributes; the unknown one is matched like any other value.
        $this->assertStringContainsString('"fein"', (string) $component->get('data.json'));
        $this->assertContains('fein', array_column((array) $component->get('unresolved'), 'value'));

        $this->createAll($component)
            ->set($this->path(LookupType::Attribute, 'fein').'.create', true)
            ->call('import')
            ->assertHasNoErrors();

        $flour = Recipe::query()->sole()->ingredients()->whereHas('food', fn ($query) => $query->where('name', 'Mehl'))->sole();
        $this->assertEqualsCanonicalizing(['weich', 'fein'], $flour->ingredientAttributes->pluck('name')->all());
    }

    public function test_too_long_attributes_of_a_new_food_are_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = $this->resolveStep()
            ->set($this->path(LookupType::Food, 'Mehl').'.create', true)
            ->set($this->path(LookupType::Food, 'Mehl').'.attributes', [str_repeat('x', RecipeJsonSchema::MAX_ATTRIBUTE_NAME + 1)])
            ->assertHasErrors([$this->path(LookupType::Food, 'Mehl').'.attributes']);

        $this->assertStringNotContainsString('xxxxx', (string) $component->get('data.json'));
    }

    public function test_an_admin_stores_conversions_on_a_created_food(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->createAll($this->resolveStep())
            ->set($this->path(LookupType::Food, 'Apfel').'.conversion', [
                'measure' => 'mass',
                'density' => '0.8',
                'weights' => ['w1' => ['unit_id' => $this->piece->id, 'grams' => '150']],
                // Checkbox state arrives as strings.
                'season_months' => ['9', '8', '10'],
                'storage_months' => ['1', '2'],
            ])
            ->call('import')
            ->assertHasNoErrors();

        $apple = Food::query()->where('name', 'Apfel')->sole();
        $this->assertNull($apple->requested_at);
        $this->assertSame(Measure::Mass, $apple->measure);
        $this->assertSame(0.8, $apple->density);
        $this->assertSame(150.0, $apple->unitWeights()->sole()->grams);
        $this->assertSame([8, 9, 10], $apple->season_months);
        $this->assertSame([1, 2], $apple->storage_months);

        // Without input a new food stays without conversion and season data.
        $flour = Food::query()->where('name', 'Mehl')->sole();
        $this->assertNull($flour->measure);
        $this->assertNull($flour->density);
        $this->assertSame(0, $flour->unitWeights()->count());
        $this->assertNull($flour->season_months);
        $this->assertNull($flour->storage_months);
    }

    public function test_the_season_fields_show_for_new_foods(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->resolveStep()
            ->assertFormFieldHidden($this->formPath(LookupType::Food, 'Apfel').'.conversion.season_months')
            ->set($this->path(LookupType::Food, 'Apfel').'.create', true)
            ->assertFormFieldVisible($this->formPath(LookupType::Food, 'Apfel').'.conversion.season_months')
            ->assertFormFieldVisible($this->formPath(LookupType::Food, 'Apfel').'.conversion.storage_months');
    }

    public function test_a_requested_food_keeps_its_name_and_conversions_for_the_admin(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create(['locale' => 'en']);
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);

        $this->createAll($this->resolveStep())
            ->assertSet($this->path(LookupType::Food, 'Mehl').'.name', 'Mehl')
            ->set($this->path(LookupType::Food, 'Mehl').'.name', 'Weissmehl')
            ->set($this->path(LookupType::Tag, 'Herbst').'.name', 'Herbstrezept')
            ->set($this->path(LookupType::Food, 'Mehl').'.conversion', ['measure' => 'mass', 'density' => '0.55', 'weights' => []])
            ->set($this->path(LookupType::Food, 'Apfel').'.conversion.season_months', ['8', '9'])
            ->set($this->path(LookupType::Food, 'Apfel').'.conversion.storage_months', ['11', '12'])
            ->call('import')
            ->assertHasNoErrors();

        $flour = Food::query()->where('name', 'Weissmehl')->sole();
        $this->assertSame($user->id, $flour->requested_by);
        $this->assertSame(Measure::Mass, $flour->measure);
        $this->assertSame(0.55, $flour->density);
        $this->assertNull($flour->season_months);

        $apple = Food::query()->where('name', 'Apfel')->sole();
        $this->assertSame($user->id, $apple->requested_by);
        $this->assertSame([8, 9], $apple->season_months);
        $this->assertSame([11, 12], $apple->storage_months);
        $this->assertDatabaseMissing('foods', ['name' => 'Mehl']);
        $this->assertSame(1, Recipe::query()->sole()->ingredients()->where('food_id', $flour->id)->count());
        $this->assertTrue(Recipe::query()->sole()->tags()->where('name', 'Herbstrezept')->exists());

        Mail::assertSent(LookupRequestedMail::class, fn (LookupRequestedMail $mail): bool => $mail->hasTo($admin->email)
            && $mail->requests === [['type' => 'tag', 'value' => 'Herbstrezept'], ['type' => 'food', 'value' => 'Weissmehl'], ['type' => 'food', 'value' => 'Apfel']]);

        // The admin sees and adjusts them before approving.
        $this->actingAs($admin);
        Livewire::test(EditFood::class, ['record' => $flour->getRouteKey()])
            ->assertSchemaStateSet(['density' => 0.55])
            ->fillForm(['density' => 0.6, 'unitWeights' => [['unit_id' => $this->piece->id, 'grams' => 5]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0.6, $flour->fresh()?->density);
        $this->assertSame(5.0, $flour->unitWeights()->sole()->grams);
    }

    public function test_conversions_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $volume = Unit::query()->where('name', 'g')->sole();

        foreach ([
            ['measure' => null, 'density' => '50', 'weights' => []],
            ['measure' => 'weight', 'density' => null, 'weights' => []],
            ['measure' => null, 'density' => null, 'weights' => ['w1' => ['unit_id' => $this->piece->id, 'grams' => '0']]],
            // Only piece units (without measure) get a weight.
            ['measure' => null, 'density' => null, 'weights' => ['w1' => ['unit_id' => $volume->id, 'grams' => '10']]],
            // Months 1–12, each once.
            ['measure' => null, 'density' => null, 'weights' => [], 'season_months' => ['13']],
            ['measure' => null, 'density' => null, 'weights' => [], 'season_months' => ['0', '5']],
            ['measure' => null, 'density' => null, 'weights' => [], 'storage_months' => ['3', '3']],
            ['measure' => null, 'density' => null, 'weights' => [], 'storage_months' => ['März']],
            ['measure' => null, 'density' => null, 'weights' => [], 'season_months' => '5'],
        ] as $conversion) {
            $this->createAll($this->resolveStep())
                ->set($this->path(LookupType::Food, 'Apfel').'.conversion', $conversion)
                ->call('import')
                ->assertHasErrors();
        }

        $this->assertSame(0, Recipe::query()->count());
        $this->assertDatabaseMissing('foods', ['name' => 'Apfel']);
        $this->assertDatabaseMissing('foods', ['name' => 'Mehl']);
    }

    public function test_an_existing_name_selects_the_existing_entry(): void
    {
        Mail::fake();
        User::factory()->admin()->create();
        $this->actingAs(User::factory()->create(['admin' => false]));
        $spelt = Food::factory()->create(['name' => 'Dinkelmehl']);

        $component = $this->createAll($this->resolveStep())
            ->set($this->path(LookupType::Food, 'Mehl').'.name', 'dinkelmehl')
            ->assertNotified(__('":name" already exists and was selected instead.', ['name' => 'Dinkelmehl']))
            ->assertSet($this->path(LookupType::Food, 'Mehl').'.create', false)
            ->assertSet($this->path(LookupType::Food, 'Mehl').'.id', $spelt->id);

        // Also when "create" is switched on again: the existing entry is used, nothing requested.
        $component
            ->set($this->path(LookupType::Food, 'Mehl').'.create', true)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame(1, Food::query()->where('name', 'Dinkelmehl')->count());
        $this->assertDatabaseMissing('foods', ['name' => 'Mehl']);
        $this->assertSame(1, Recipe::query()->sole()->ingredients()->where('food_id', $spelt->id)->count());
        Mail::assertSent(LookupRequestedMail::class, fn (LookupRequestedMail $mail): bool => ! in_array('dinkelmehl', array_map('mb_strtolower', array_column($mail->requests, 'value')), true));
    }

    public function test_the_name_of_a_new_entry_is_required_and_limited(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (['', str_repeat('x', LookupType::Food->maxLength() + 1)] as $name) {
            $this->createAll($this->resolveStep())
                ->set($this->path(LookupType::Food, 'Mehl').'.name', $name)
                ->call('import')
                ->assertHasErrors();
        }

        $this->assertSame(0, Recipe::query()->count());
        $this->assertSame(0, Food::query()->where('name', 'like', 'xxx%')->count());
    }

    public function test_a_too_long_name_shows_the_error_until_it_is_shortened(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $name = $this->path(LookupType::Food, 'Mehl').'.name';

        $component = $this->createAll($this->resolveStep())
            ->set($name, str_repeat('x', LookupType::Food->maxLength() + 1))
            ->assertHasErrors([$name]);

        $component->set($name, 'Weissmehl')
            ->assertHasNoErrors([$name])
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('foods', ['name' => 'Weissmehl']);
    }

    public function test_the_same_new_name_twice_is_created_and_requested_once(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs(User::factory()->create(['admin' => false]));

        $this->createAll($this->resolveStep())
            ->set($this->path(LookupType::Food, 'Mehl').'.name', 'Apfelmehl')
            ->set($this->path(LookupType::Food, 'Apfel').'.name', 'Apfelmehl')
            ->set($this->path(LookupType::Food, 'Mehl').'.conversion.density', '0.5')
            ->set($this->path(LookupType::Food, 'Apfel').'.conversion.density', '0.9')
            ->call('import')
            ->assertHasNoErrors()
            ->assertNotified(__('Conversion and season data not saved'));

        // The first one wins, the second one's data is reported as not saved.
        $food = Food::query()->where('name', 'Apfelmehl')->sole();
        $this->assertSame(0.5, $food->density);
        $this->assertSame(2, Recipe::query()->sole()->ingredients()->where('food_id', $food->id)->count());
        Mail::assertSent(LookupRequestedMail::class, fn (LookupRequestedMail $mail): bool => $mail->hasTo($admin->email)
            && $mail->requests === [['type' => 'tag', 'value' => 'Herbst'], ['type' => 'food', 'value' => 'Apfelmehl']]);
    }

    public function test_conversions_of_a_reused_entry_are_reported_and_the_summary_names_existing_entries(): void
    {
        Mail::fake();
        User::factory()->admin()->create();
        $this->actingAs(User::factory()->create(['admin' => false]));
        // Another user's request: not visible, so the name check cannot select it, create() reuses it.
        $foreign = Food::factory()->create(['name' => 'Urmehl', 'requested_by' => User::factory()->create()->id, 'requested_at' => now()]);

        $this->createAll($this->resolveStep())
            ->set($this->path(LookupType::Food, 'Mehl').'.name', 'Urmehl')
            ->set($this->path(LookupType::Food, 'Mehl').'.conversion.density', '0.5')
            ->call('import')
            ->assertHasNoErrors()
            ->assertNotified(__('Conversion and season data not saved'));

        $this->assertNull($foreign->fresh()?->density);
        Mail::assertSent(LookupRequestedMail::class, fn (LookupRequestedMail $mail): bool => ! in_array('Urmehl', array_column($mail->requests, 'value'), true));

        // "Next" checks the names like leaving the field; the summary does not call an existing entry requested.
        // A draft starts on "Resolve", so the test's "Next" leaves that step.
        $draft = RecipeDraft::query()->create([
            'user_id' => user()?->id,
            'data' => [...RecipeJsonSchema::example(), 'name' => 'Zweiter Kuchen', 'cookbook' => null],
            'expires_at' => now()->addHour(),
        ]);

        $component = Livewire::withQueryParams(['draft' => $draft->id])
            ->test(ImportRecipe::class)
            ->assertWizardCurrentStep(2)
            // "create" switched on after the name: only the check on "Next" can switch it off again.
            ->set($this->path(LookupType::Food, 'Mehl').'.name', 'Margarine')
            ->set($this->path(LookupType::Food, 'Mehl').'.create', true)
            ->goToNextWizardStep()
            ->assertHasNoErrors()
            ->assertWizardCurrentStep(3)
            ->assertNotified(__('":name" already exists and was selected instead.', ['name' => 'Margarine']))
            ->assertSet($this->path(LookupType::Food, 'Mehl').'.create', false);

        // Switched on again on "Confirm": listed as existing, not as requested.
        $component->set($this->path(LookupType::Food, 'Mehl').'.create', true)
            ->assertSee(__('Existing entries used').': '.__('Food').' &quot;Margarine&quot;', false)
            ->assertDontSee(__('Requested').': ', false);
    }

    public function test_the_ai_estimates_conversions_of_all_new_foods(): void
    {
        $this->enableAi();
        Http::fake(['openai.test/*' => Http::response($this->answer(['foods' => [
            // No Swiss season: empty months.
            ['name' => 'Mehl', 'measure' => 'mass', 'density' => 0.55, 'piece_weights' => [], 'season_months' => [], 'storage_months' => []],
            // Out of range and unknown units, invalid and repeated months, storage months in season are dropped.
            ['name' => 'apfel', 'measure' => 'mass', 'density' => 40, 'piece_weights' => [['unit' => 'Stück', 'grams' => 150], ['unit' => 'Tasse', 'grams' => 100], ['unit' => 'Prise', 'grams' => -1]],
                'season_months' => [10, 8, 9, 9, 13], 'storage_months' => [11, 12, 1, 10, 0]],
        ]]))]);
        $user = User::factory()->create(['admin' => false]);
        $this->actingAs($user);

        $component = $this->createAll($this->resolveStep())
            ->set($this->path(LookupType::Food, 'Apfel').'.conversion.density', '0.7')
            // Months ticked by the user stay.
            ->set($this->path(LookupType::Food, 'Apfel').'.conversion.storage_months', ['2'])
            ->callAction(TestAction::make('estimateConversions')->schemaComponent('conversionActions'))
            ->assertNotified(__('Conversions estimated. Please check them.'));

        $this->assertSame('mass', $component->get($this->path(LookupType::Food, 'Mehl').'.conversion.measure'));
        $this->assertSame(0.55, $component->get($this->path(LookupType::Food, 'Mehl').'.conversion.density'));
        $this->assertSame([], $component->get($this->path(LookupType::Food, 'Mehl').'.conversion.season_months'));

        $apple = $component->get($this->path(LookupType::Food, 'Apfel').'.conversion');
        $this->assertSame('mass', $apple['measure']);
        $this->assertSame('0.7', $apple['density']);
        $this->assertSame([['unit_id' => $this->piece->id, 'grams' => 150.0]], array_values($apple['weights']));
        $this->assertSame([8, 9, 10], $apple['season_months']);
        $this->assertSame(['2'], $apple['storage_months']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $input = json_decode($request['input'][0]['content'][0]['text'], true);
            $food = $request['text']['format']['schema']['properties']['foods']['items'];

            return $request['text']['format']['strict'] === true
                && $input['foods'] === [['name' => 'Mehl', 'units_in_recipe' => ['g']], ['name' => 'Apfel', 'units_in_recipe' => []]]
                && $input['piece_units'] === ['Prise', 'Stück']
                && in_array('season_months', $food['required'], true)
                && in_array('storage_months', $food['required'], true)
                && $food['properties']['season_months']['items']['enum'] === range(1, 12)
                && str_contains($request['instructions'], 'Swiss seasonal calendar');
        });
        $this->assertDatabaseHas('ai_usages', ['user_id' => $user->id, 'feature' => 'conversion', 'input_tokens' => 50, 'images' => 0]);

        // The estimate is stored with the requested food.
        $component->call('import')->assertHasNoErrors();

        $flour = Food::query()->where('name', 'Mehl')->sole();
        $this->assertSame(Measure::Mass, $flour->measure);
        $this->assertSame(0.55, $flour->density);
        $this->assertNull($flour->season_months);

        $apple = Food::query()->where('name', 'Apfel')->sole();
        $this->assertSame(150.0, $apple->unitWeights()->sole()->grams);
        $this->assertSame([8, 9, 10], $apple->season_months);
        $this->assertSame([2], $apple->storage_months);
    }

    public function test_a_failed_or_limited_estimate_only_warns(): void
    {
        $this->enableAi(limit: 1);
        Http::fake(['openai.test/*' => Http::response(['error' => 'down'], 500)]);
        $this->actingAs(User::factory()->create());

        $component = $this->createAll($this->resolveStep())
            ->callAction(TestAction::make('estimateConversions')->schemaComponent('conversionActions'))
            ->assertNotified(__('The conversions could not be estimated.'))
            ->assertSet($this->path(LookupType::Food, 'Mehl').'.conversion.measure', null);

        // The failed request counts against the hourly limit.
        $component->callAction(TestAction::make('estimateConversions')->schemaComponent('conversionActions'))
            ->assertNotified(__('The conversions could not be estimated.'));
        Http::assertSentCount(1);

        $component->call('import')->assertHasNoErrors();
        $this->assertNull(Food::query()->where('name', 'Mehl')->sole()->measure);
    }

    public function test_the_estimate_needs_ai_access_and_a_new_food(): void
    {
        $this->actingAs(User::factory()->create());

        // Without key.
        $this->createAll($this->resolveStep())
            ->assertSchemaComponentHidden('conversionActions', 'form');

        $this->enableAi();

        $this->resolveStep()
            ->assertSchemaComponentHidden('conversionActions', 'form')
            ->set($this->path(LookupType::Food, 'Mehl').'.create', true)
            ->assertSchemaComponentVisible('conversionActions', 'form')
            ->assertActionVisible(TestAction::make('estimateConversions')->schemaComponent('conversionActions'));
    }
}
