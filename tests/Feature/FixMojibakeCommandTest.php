<?php

namespace Tests\Feature;

use App\Console\Commands\FixMojibake;
use App\Models\Food;
use App\Models\Recipe;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class FixMojibakeCommandTest extends TestCase
{
    public function test_it_repairs_double_encoded_text(): void
    {
        $this->assertSame('½ Std. bei 220° backen', FixMojibake::fix('Â½ Std. bei 220Â° backen'));
        $this->assertSame('Äpfel, Ø 24 cm, Crème, Über, 20×20 cm², à 50 g', FixMojibake::fix("Ã„pfel, Ã˜ 24 cm, CrÃ¨me, Ãœber, 20Ã—20 cmÂ², Ã\u{A0} 50 g"));
        $this->assertSame('1–1¼ Std. „gut“', FixMojibake::fix('1â€“1Â¼ Std. â€žgutâ€œ'));
    }

    public function test_a_decoded_sharp_s_becomes_ss(): void
    {
        $this->assertSame('Eiweiss heiss', FixMojibake::fix('EiweiÃŸ heiÃŸ'));
    }

    public function test_correct_text_is_left_alone(): void
    {
        foreach (['Rüebli, Äpfel, Öl, Crème brûlée', '2×½ Tasse', 'Süss-saure Gurken à 250 g', 'Ø 24 cm, 180 °C'] as $text) {
            $this->assertSame($text, FixMojibake::fix($text));
        }
    }

    public function test_the_command_fixes_recipes_and_lookups_and_keeps_the_timestamps(): void
    {
        $recipe = Recipe::factory()->create(['instructions' => 'Â½ Std. ruhen lassen']);
        $recipe->delete();
        $food = Food::factory()->create(['name' => 'Ã„pfel']);
        $updatedAt = $recipe->fresh()->updated_at;

        $this->fix(['--dry-run' => true])
            ->expectsOutputToContain('2 records would be fixed')
            ->assertSuccessful();

        $this->assertSame('Ã„pfel', $food->fresh()->name);

        $this->fix()->expectsOutputToContain('2 records fixed')->assertSuccessful();

        $this->assertSame('½ Std. ruhen lassen', Recipe::withTrashed()->find($recipe->id)->instructions);
        $this->assertEquals($updatedAt, Recipe::withTrashed()->find($recipe->id)->updated_at);
        $this->assertSame('Äpfel', $food->fresh()->name);

        $this->fix()->expectsOutputToContain('No broken text found.')->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function fix(array $options = []): PendingCommand
    {
        $command = $this->artisan('text:fix-encoding', $options);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }
}
