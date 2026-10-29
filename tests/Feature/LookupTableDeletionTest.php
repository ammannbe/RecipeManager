<?php

namespace Tests\Feature;

use App\Filament\Resources\Foods\Pages\ListFoods;
use App\Filament\Resources\IngredientAttributes\Pages\ListIngredientAttributes;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientAttribute;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lookups can be deleted from the list like on the edit page, but only while unused.
 */
class LookupTableDeletionTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: callable(): array{0: Model, 1: Model}}>
     */
    public static function lookups(): array
    {
        return [
            'food' => [ListFoods::class, function (): array {
                return [Food::factory()->create(), Ingredient::factory()->create()->food];
            }],
            'unit' => [ListUnits::class, function (): array {
                $used = Unit::factory()->create();
                Ingredient::factory()->create(['unit_id' => $used->id]);

                return [Unit::factory()->create(), $used];
            }],
            'attribute' => [ListIngredientAttributes::class, function (): array {
                $used = IngredientAttribute::factory()->create();
                Ingredient::factory()->create()->ingredientAttributes()->attach($used);

                return [IngredientAttribute::factory()->create(), $used];
            }],
            'tag' => [ListTags::class, function (): array {
                $used = Tag::factory()->create();
                Recipe::factory()->create()->tags()->attach($used);

                return [Tag::factory()->create(), $used];
            }],
        ];
    }

    /**
     * @param  class-string  $page
     * @param  callable(): array{0: Model, 1: Model}  $records
     */
    #[DataProvider('lookups')]
    public function test_an_admin_deletes_unused_entries_from_the_table(string $page, callable $records): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$unused, $used] = $records();

        Livewire::test($page)
            ->assertActionVisible(TestAction::make(DeleteAction::class)->table($unused))
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($used))
            ->callAction(TestAction::make(DeleteAction::class)->table($unused));

        $this->assertSoftDeleted($unused);
        $this->assertNotSoftDeleted($used);
    }

    /**
     * @param  class-string  $page
     * @param  callable(): array{0: Model, 1: Model}  $records
     */
    #[DataProvider('lookups')]
    public function test_bulk_deletion_skips_entries_still_in_use(string $page, callable $records): void
    {
        $this->actingAs(User::factory()->admin()->create());
        [$unused, $used] = $records();

        Livewire::test($page)
            ->selectTableRecords([$unused->getKey(), $used->getKey()])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertSoftDeleted($unused);
        $this->assertNotSoftDeleted($used);
    }

    public function test_a_non_admin_sees_no_delete_action(): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]));
        $food = Food::factory()->create();

        Livewire::test(ListFoods::class)
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($food));
    }
}
