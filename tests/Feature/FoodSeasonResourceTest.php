<?php

namespace Tests\Feature;

use App\Filament\Resources\Foods\Pages\CreateFood;
use App\Filament\Resources\Foods\Pages\EditFood;
use App\Filament\Resources\Foods\Pages\ListFoods;
use App\Models\Food;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class FoodSeasonResourceTest extends TestCase
{
    public function test_an_admin_saves_season_and_storage_months(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $food = Food::factory()->create(['name' => 'Rüebli']);

        Livewire::test(EditFood::class, ['record' => $food->getRouteKey()])
            ->fillForm(['season_months' => ['7', '6'], 'storage_months' => ['12', '1']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([6, 7], $food->fresh()->season_months);
        $this->assertSame([1, 12], $food->fresh()->storage_months);

        Livewire::test(EditFood::class, ['record' => $food->getRouteKey()])
            ->assertFormSet(['season_months' => [6, 7]])
            ->fillForm(['season_months' => [], 'storage_months' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($food->fresh()->season_months);
        $this->assertNull($food->fresh()->storage_months);
    }

    public function test_months_can_be_set_when_creating_a_food(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateFood::class)
            ->fillForm(['name' => 'Spargel', 'season_months' => ['4', '5', '6']])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame([4, 5, 6], Food::query()->where('name', 'Spargel')->firstOrFail()->season_months);
    }

    public function test_the_list_shows_and_filters_season_data(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $winter = Food::factory()->create(['season_months' => [11, 12, 1, 2]]);
        $none = Food::factory()->create();

        Livewire::test(ListFoods::class)
            ->assertTableColumnStateSet('season', 'Nov–Feb', $winter)
            ->filterTable('season_data', true)
            ->assertCanSeeTableRecords([$winter])
            ->assertCanNotSeeTableRecords([$none])
            ->filterTable('season_data', false)
            ->assertCanSeeTableRecords([$none])
            ->assertCanNotSeeTableRecords([$winter]);
    }
}
