<?php

namespace Tests\Feature;

use App\Enums\Measure;
use App\Filament\Resources\Foods\FoodResource;
use App\Filament\Resources\Foods\Pages\EditFood;
use App\Filament\Resources\Foods\Pages\ListFoods;
use App\Filament\Resources\Units\Pages\EditUnit;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Http\Middleware\SetLocale;
use App\Models\Category;
use App\Models\Food;
use App\Models\FoodUnitWeight;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use App\Services\Lookup\LookupMerger;
use App\Services\RecipeImport\RecipeImporter;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipeImport\RecipeJsonValidator;
use App\Services\UnitConverter;
use Illuminate\Http\Request;
use Illuminate\Testing\PendingCommand;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Tests\TestCase;

class UnitConversionTest extends TestCase
{
    private function unit(string $name, ?Measure $measure = null, ?float $base = null, ?string $shortcut = null): Unit
    {
        return Unit::factory()->create([
            'name' => $name,
            'name_shortcut' => $shortcut,
            'name_plural' => null,
            'name_plural_shortcut' => null,
            'measure' => $measure,
            'base_amount' => $base,
        ]);
    }

    private function ingredient(Recipe $recipe, ?float $amount, Unit $unit, Food $food, int $position = 1): Ingredient
    {
        return Ingredient::factory()->create([
            'recipe_id' => $recipe->id,
            'ingredient_group_id' => null,
            'ingredient_id' => null,
            'position' => $position,
            'amount' => $amount,
            'amount_max' => null,
            'unit_id' => $unit->id,
            'food_id' => $food->id,
        ]);
    }

    /**
     * @return array{measure: Measure, factor: float}|null
     */
    private function factor(Ingredient $ingredient): ?array
    {
        return app(UnitConverter::class)->factor($ingredient->fresh(['unit', 'food.unitWeights']));
    }

    public function test_it_converts_within_a_measure_across_measures_and_pieces(): void
    {
        $recipe = Recipe::factory()->create(['cookbook_id' => null]);
        $spoon = $this->unit('Esslöffel', Measure::Volume, 15, 'EL');
        $piece = $this->unit('Stück');

        $oil = Food::factory()->create(['measure' => Measure::Volume, 'density' => 0.92]);
        $flour = Food::factory()->create(['measure' => Measure::Mass, 'density' => 0.55]);
        $water = Food::factory()->create();
        $sugar = Food::factory()->create(['measure' => Measure::Mass]);
        $egg = Food::factory()->create(['measure' => Measure::Mass]);
        FoodUnitWeight::query()->create(['food_id' => $egg->id, 'unit_id' => $piece->id, 'grams' => 55]);

        $this->assertEquals(['measure' => Measure::Volume, 'factor' => 15.0], $this->factor($this->ingredient($recipe, 2, $spoon, $oil, 1)));
        $this->assertEqualsWithDelta(8.25, $this->factor($this->ingredient($recipe, 2, $spoon, $flour, 2))['factor'], 0.001);
        // Without a measure the amount keeps its own (EL → ml).
        $this->assertEquals(['measure' => Measure::Volume, 'factor' => 15.0], $this->factor($this->ingredient($recipe, 1, $spoon, $water, 3)));
        // Mass wanted but no density: not convertible.
        $this->assertNull($this->factor($this->ingredient($recipe, 1, $spoon, $sugar, 4)));
        $this->assertEquals(['measure' => Measure::Mass, 'factor' => 55.0], $this->factor($this->ingredient($recipe, 3, $piece, $egg, 5)));
        $this->assertNull($this->factor($this->ingredient($recipe, 3, $piece, $sugar, 6)));
    }

    public function test_display_picks_the_unit_and_rounds(): void
    {
        $this->assertSame(['amount' => 42.0, 'amount_max' => null, 'unit' => 'g'], UnitConverter::display(Measure::Mass, 41.85, null));
        $this->assertSame(['amount' => 4.5, 'amount_max' => null, 'unit' => 'g'], UnitConverter::display(Measure::Mass, 4.5, null));
        $this->assertSame(['amount' => 1.2, 'amount_max' => 1.5, 'unit' => 'kg'], UnitConverter::display(Measure::Mass, 1200, 1500));
        $this->assertSame(['amount' => 45.0, 'amount_max' => null, 'unit' => 'ml'], UnitConverter::display(Measure::Volume, 45, null));
        $this->assertSame(['amount' => 2.5, 'amount_max' => null, 'unit' => 'dl'], UnitConverter::display(Measure::Volume, 250, null));
        $this->assertSame(['amount' => 1.25, 'amount_max' => null, 'unit' => 'l'], UnitConverter::display(Measure::Volume, 1250, null));
    }

    public function test_amounts_needing_more_than_two_decimals_move_to_a_smaller_unit(): void
    {
        $liter = $this->unit('Liter', Measure::Volume, 1000, 'l');
        $deciliter = $this->unit('Deziliter', Measure::Volume, 100, 'dl');
        $milliliter = $this->unit('Milliliter', Measure::Volume, 1, 'ml');
        $kilo = $this->unit('Kilogramm', Measure::Mass, 1000, 'kg');
        $gram = $this->unit('Gramm', Measure::Mass, 1, 'g');
        $spoon = $this->unit('Esslöffel', Measure::Volume, 15, 'EL');
        $converter = app(UnitConverter::class);

        $result = $converter->precise($liter, 0.125, null);
        $this->assertTrue($result['unit']->is($deciliter));
        $this->assertSame(1.25, $result['amount']);

        $result = $converter->precise($liter, 0.0625, 0.125);
        $this->assertTrue($result['unit']->is($milliliter));
        $this->assertSame([62.5, 125.0], [$result['amount'], $result['amount_max']]);

        $result = $converter->precise($kilo, 0.125, null);
        $this->assertTrue($result['unit']->is($gram));
        $this->assertSame(125.0, $result['amount']);

        // Two decimals, other units and the smallest unit stay as they are.
        $this->assertTrue($converter->precise($liter, 0.25, null)['unit']->is($liter));
        $this->assertSame(0.125, $converter->precise($spoon, 0.125, null)['amount']);
        $this->assertSame(0.125, $converter->precise($milliliter, 0.125, null)['amount']);
    }

    public function test_the_import_keeps_the_precision_of_fractions(): void
    {
        $this->unit('Liter', Measure::Volume, 1000, 'l');
        $deciliter = $this->unit('Deziliter', Measure::Volume, 100, 'dl');
        Category::factory()->create(['name' => 'Dessert']);
        Food::factory()->create(['name' => 'Wasser']);
        $user = User::factory()->admin()->create();

        $recipe = app(RecipeImporter::class)->import(app(RecipeJsonValidator::class)->validate((string) json_encode([
            ...RecipeJsonSchema::example(),
            'cookbook' => null,
            'tags' => [],
            'ingredient_groups' => [],
            'ingredients' => [['amount' => 0.125, 'amount_max' => null, 'unit' => 'l', 'food' => 'Wasser', 'attributes' => []]],
        ])), $user);

        $ingredient = $recipe->ingredients()->sole();
        $this->assertSame($deciliter->id, $ingredient->unit_id);
        $this->assertEquals(1.25, $ingredient->amount);
    }

    public function test_the_recipe_page_offers_convert_only_when_it_changes_something(): void
    {
        $gram = $this->unit('Gramm', Measure::Mass, 1, 'g');
        $spoon = $this->unit('Esslöffel', Measure::Volume, 15, 'EL');
        $flour = Food::factory()->create(['name' => 'Mehl', 'measure' => Measure::Mass, 'density' => 0.55]);

        $metric = Recipe::factory()->create(['cookbook_id' => null]);
        $this->ingredient($metric, 200, $gram, $flour);

        $this->get(route('recipes.show', $metric))
            ->assertSuccessful()
            ->assertDontSee('data-convert-units', escape: false);

        $spoons = Recipe::factory()->create(['cookbook_id' => null]);
        $this->ingredient($spoons, 2, $spoon, $flour);

        $this->get(route('recipes.show', $spoons))
            ->assertSuccessful()
            ->assertSee('data-convert-units', escape: false)
            ->assertSee('Converted amounts are approximate')
            ->assertSee('\u0022measure\u0022:\u0022mass\u0022', escape: false);
    }

    public function test_the_api_returns_converted_amounts_scaled_by_servings(): void
    {
        $spoon = $this->unit('Esslöffel', Measure::Volume, 15, 'EL');
        $piece = $this->unit('Stück');
        $oil = Food::factory()->create(['name' => 'Öl', 'measure' => Measure::Volume, 'density' => 0.92]);
        $onion = Food::factory()->create(['name' => 'Zwiebeln', 'measure' => Measure::Mass]);
        $salt = Food::factory()->create(['name' => 'Salz']);

        $recipe = Recipe::factory()->create(['cookbook_id' => null, 'is_public' => true, 'servings' => 2]);
        $this->ingredient($recipe, 2, $spoon, $oil, 1);
        $this->ingredient($recipe, 1, $piece, $onion, 2);
        $this->ingredient($recipe, null, $spoon, $salt, 3);

        Passport::actingAs(User::factory()->create(), ['recipes']);

        $ingredients = $this->getJson("/api/recipes/{$recipe->id}?servings=4")->assertOk()->json('data.ingredients');

        $this->assertSame(['amount' => 60, 'amount_max' => null, 'unit' => 'ml'], $ingredients[0]['converted']);
        // No piece weight for this onion: not convertible.
        $this->assertNull($ingredients[1]['converted']);
        $this->assertNull($ingredients[2]['converted']);
    }

    public function test_admins_edit_conversion_data(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $unit = $this->unit('Tasse');
        $piece = $this->unit('Bund');
        $food = Food::factory()->create();

        Livewire::test(EditUnit::class, ['record' => $unit->getRouteKey()])
            ->fillForm(['measure' => 'volume', 'base_amount' => 200])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Measure::Volume, $unit->fresh()->measure);
        $this->assertSame(200.0, $unit->fresh()->base_amount);

        Livewire::test(EditFood::class, ['record' => $food->getRouteKey()])
            ->fillForm([
                'measure' => 'mass',
                'density' => 0.2,
                'unitWeights' => [['unit_id' => $piece->id, 'grams' => 30]],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $food->refresh();
        $this->assertSame(Measure::Mass, $food->measure);
        $this->assertSame(0.2, $food->density);
        $this->assertSame(30.0, $food->unitWeights()->sole()->grams);
    }

    public function test_the_lists_show_and_filter_conversion_data(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $piece = $this->unit('Stück');
        $spoon = $this->unit('Esslöffel', Measure::Volume, 15, 'EL');
        $oil = Food::factory()->create(['measure' => Measure::Volume, 'density' => 0.92]);
        $egg = Food::factory()->create();
        FoodUnitWeight::query()->create(['food_id' => $egg->id, 'unit_id' => $piece->id, 'grams' => 55]);
        $none = Food::factory()->create();

        Livewire::test(ListFoods::class)
            ->assertTableColumnStateSet('conversion', 'Volume · 0.92 g/ml', $oil)
            ->assertTableColumnStateSet('conversion', '1 Stück = 55 g', $egg)
            ->filterTable('conversion_data', false)
            ->assertCanSeeTableRecords([$none])
            ->assertCanNotSeeTableRecords([$oil, $egg]);

        Livewire::test(ListUnits::class)
            ->assertTableColumnStateSet('base_amount', '1 EL = 15 ml', $spoon);
    }

    public function test_the_lists_write_decimals_like_the_browser(): void
    {
        $this->actingAs(User::factory()->admin()->create(['locale' => 'de']));
        Food::factory()->create(['measure' => Measure::Volume, 'density' => 0.92]);

        // German from Switzerland writes a decimal point, from Germany a comma.
        $this->withHeader('Accept-Language', 'de-CH,de;q=0.9')->get(FoodResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Volumen · 0.92 g/ml');
        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get(FoodResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Volumen · 0,92 g/ml');
    }

    public function test_the_number_locale_follows_the_browser_within_the_app_language(): void
    {
        app()->setLocale('de');

        foreach (['de-CH,de;q=0.9' => 'de_CH', 'de-DE' => 'de_DE', 'en-US,de-AT;q=0.5' => 'de_AT', 'fr-CH' => 'de'] as $header => $locale) {
            $this->app->instance('request', Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => $header]));
            $this->assertSame($locale, SetLocale::numberLocale(), $header);
        }
    }

    public function test_merging_keeps_the_piece_weights(): void
    {
        $piece = $this->unit('Stück');
        $request = $this->unit('Stk');
        $food = Food::factory()->create();
        $other = Food::factory()->create();
        FoodUnitWeight::query()->create(['food_id' => $food->id, 'unit_id' => $request->id, 'grams' => 10]);
        FoodUnitWeight::query()->create(['food_id' => $other->id, 'unit_id' => $request->id, 'grams' => 20]);
        FoodUnitWeight::query()->create(['food_id' => $other->id, 'unit_id' => $piece->id, 'grams' => 25]);

        app(LookupMerger::class)->merge($request, $piece);

        $this->assertSame(10.0, FoodUnitWeight::query()->where('food_id', $food->id)->sole()->grams);
        // The target's own weight wins.
        $this->assertSame(25.0, FoodUnitWeight::query()->where('food_id', $other->id)->sole()->grams);
        $this->assertSame(0, FoodUnitWeight::query()->where('unit_id', $request->id)->count());
    }

    public function test_the_command_assigns_data_and_keeps_manual_values(): void
    {
        $spoon = $this->unit('Esslöffel');
        $piece = $this->unit('Stück');
        $oil = Food::factory()->create(['name' => 'Öl']);
        $flour = Food::factory()->create(['name' => 'Mehl', 'measure' => Measure::Mass, 'density' => 0.6]);
        $eggs = Food::factory()->create(['name' => 'Eier']);

        $command = $this->artisan('foods:conversions', ['--dry-run' => true]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertNull($spoon->fresh()->measure);

        $command = $this->artisan('foods:conversions');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertSame(Measure::Volume, $spoon->fresh()->measure);
        $this->assertSame(15.0, $spoon->fresh()->base_amount);
        $this->assertSame(0.92, $oil->fresh()->density);
        $this->assertSame(0.6, $flour->fresh()->density, 'set by hand');
        $this->assertSame(55.0, FoodUnitWeight::query()->where(['food_id' => $eggs->id, 'unit_id' => $piece->id])->sole()->grams);

        $command = $this->artisan('foods:conversions', ['--force' => true]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertSame(0.55, $flour->fresh()->density);
    }
}
