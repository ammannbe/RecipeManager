<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\ImportPromptBuilder;
use Tests\TestCase;

class ImportPromptTest extends TestCase
{
    public function test_it_lists_the_small_lookup_tables(): void
    {
        Category::factory()->create(['name' => 'Dessert']);
        Unit::factory()->create(['name' => 'Gramm', 'name_shortcut' => 'g']);

        $prompt = app(ImportPromptBuilder::class)->build(User::factory()->admin()->create());

        $this->assertStringContainsString('Dessert', $prompt);
        $this->assertStringContainsString("- Gramm\n", $prompt);
    }

    public function test_it_lists_units_without_their_shortcut(): void
    {
        Unit::factory()->create(['name' => 'Gramm', 'name_shortcut' => 'g']);

        $prompt = app(ImportPromptBuilder::class)->build(User::factory()->admin()->create());

        $this->assertStringNotContainsString('Gramm (g)', $prompt);
    }

    public function test_it_never_lists_foods(): void
    {
        Food::factory()->create(['name' => 'Pastinakenpüree']);

        $prompt = app(ImportPromptBuilder::class)->build(User::factory()->admin()->create());

        $this->assertStringNotContainsString('Pastinakenpüree', $prompt);
    }

    public function test_it_renders_one_value_per_line_so_commas_stay_intact(): void
    {
        Category::factory()->create(['name' => 'Suppen, Eintöpfe']);
        Category::factory()->create(['name' => 'Dessert']);

        $prompt = app(ImportPromptBuilder::class)->build(User::factory()->admin()->create());

        $this->assertStringContainsString("- Suppen, Eintöpfe\n", $prompt);
        $this->assertStringContainsString("- Dessert\n", $prompt);
    }

    public function test_it_only_lists_cookbooks_of_the_current_author_for_non_admins(): void
    {
        $user = User::factory()->create(['admin' => false]);

        Cookbook::factory()->create(['name' => 'Mine', 'author_id' => $user->author_id]);
        Cookbook::factory()->create(['name' => 'Theirs']);

        $prompt = app(ImportPromptBuilder::class)->build($user);

        $this->assertStringContainsString('Mine', $prompt);
        $this->assertStringNotContainsString('Theirs', $prompt);
    }

    public function test_it_truncates_long_lists(): void
    {
        Category::factory()->count(ImportPromptBuilder::MAX_LIST_ENTRIES + 5)->create();

        $prompt = app(ImportPromptBuilder::class)->build(User::factory()->admin()->create());

        $this->assertStringContainsString(
            (string) ImportPromptBuilder::MAX_LIST_ENTRIES,
            $prompt
        );
    }

    public function test_it_stays_reasonably_small(): void
    {
        Category::factory()->count(20)->create();
        Unit::factory()->count(50)->create();

        for ($i = 0; $i < 500; $i++) {
            Food::query()->create(['name' => 'Lebensmittel '.$i]);
        }

        $prompt = app(ImportPromptBuilder::class)->build(User::factory()->admin()->create());

        // Roughly 4k tokens at ~4 bytes per token.
        $this->assertLessThan(16000, strlen($prompt));
    }

    public function test_it_contains_the_example_and_the_allowed_complexities(): void
    {
        $prompt = app(ImportPromptBuilder::class)->build(User::factory()->admin()->create());

        $this->assertStringContainsString('"ingredient_groups"', $prompt);
        $this->assertStringContainsString('difficult', $prompt);
    }
}
