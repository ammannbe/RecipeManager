<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\User;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class FoodSeasonsCommandTest extends TestCase
{
    /**
     * @param  array<string, bool>  $options
     */
    private function seasons(array $options = []): PendingCommand
    {
        $command = $this->artisan('foods:seasons', $options);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    public function test_months_are_normalized_to_sorted_integers(): void
    {
        $food = Food::factory()->create(['season_months' => ['7', 5, '7', 13], 'storage_months' => []]);

        $this->assertSame([5, 7], $food->fresh()->season_months);
        $this->assertNull($food->fresh()->storage_months);
        $this->assertTrue($food->inSeason(5));
        $this->assertFalse($food->inSeason(6));
    }

    public function test_storage_months_are_not_in_season(): void
    {
        $food = Food::factory()->create(['season_months' => [8], 'storage_months' => [12]]);

        $this->assertFalse($food->inSeason(12));
    }

    public function test_it_assigns_months_to_exactly_matching_foods_and_requests(): void
    {
        $carrots = Food::factory()->create(['name' => '  rüebli ']);
        $potatoes = Food::query()->forceCreate([
            'name' => 'Kartoffeln',
            'requested_by' => User::factory()->create()->id,
            'requested_at' => now(),
        ]);
        $grated = Food::factory()->create(['name' => 'Rüebli, gerieben']);

        $this->seasons()->expectsOutputToContain('updated')->assertSuccessful();

        $this->assertSame([6, 7, 8, 9, 10], $carrots->fresh()->season_months);
        $this->assertSame([1, 2, 3, 4, 5, 11, 12], $carrots->fresh()->storage_months);
        $this->assertSame([6, 7, 8, 9, 10], $potatoes->fresh()->season_months);
        $this->assertNull($grated->fresh()->season_months);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $carrots = Food::factory()->create(['name' => 'Rüebli']);

        $this->seasons(['--dry-run' => true])
            ->expectsOutputToContain('would update')
            ->assertSuccessful();

        $this->assertNull($carrots->fresh()->season_months);
    }

    public function test_manual_months_are_only_overwritten_with_force(): void
    {
        $apples = Food::factory()->create(['name' => 'Äpfel', 'season_months' => [9]]);

        $this->seasons()->expectsOutputToContain('kept')->assertSuccessful();
        $this->assertSame([9], $apples->fresh()->season_months);

        $this->seasons(['--force' => true])->assertSuccessful();
        $this->assertSame([8, 9, 10, 11], $apples->fresh()->season_months);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 12], $apples->fresh()->storage_months);
    }

    public function test_it_reports_missing_names_and_candidates_for_manual_assignment(): void
    {
        Food::factory()->create(['name' => 'Rüebli']);
        Food::factory()->create(['name' => 'Rüebli, gerieben']);
        Food::factory()->create(['name' => 'Tomaten Dose']);

        $this->seasons(['--dry-run' => true])
            ->expectsOutputToContain('Data names without a matching food:')
            ->expectsOutputToContain('  - Spargel')
            ->expectsOutputToContain('  - Rüebli, gerieben (Rüebli)')
            ->expectsOutputToContain('  - Tomaten Dose (Tomaten)')
            ->doesntExpectOutputToContain('  - Rüebli'.PHP_EOL)
            ->assertSuccessful();
    }
}
