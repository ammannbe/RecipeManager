<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeSourceAndPhotoMetadataTest extends TestCase
{
    public function test_a_recipe_source_can_be_stored_and_shown_on_the_public_page(): void
    {
        $recipe = Recipe::factory()->create(['is_public' => true, 'source' => 'Migusto']);

        $this->get(route('recipes.show', $recipe))
            ->assertOk()
            ->assertSee('Migusto');
    }

    public function test_a_recipe_without_a_source_shows_no_source_line(): void
    {
        $recipe = Recipe::factory()->create(['is_public' => true, 'source' => null]);

        $this->get(route('recipes.show', $recipe))
            ->assertOk()
            ->assertDontSee(__('Source').':');
    }

    public function test_photos_can_carry_a_source_and_ai_generated_flag(): void
    {
        $recipe = Recipe::factory()->create();
        Storage::disk('recipes')->put($recipe->getKey().'/photo.jpg', 'binary');

        $recipe->photos = [[
            'path' => 'photo.jpg',
            'source' => 'Unsplash',
            'is_ai_generated' => true,
        ]];
        $recipe->save();

        $document = $recipe->refresh()->photos->first();

        $this->assertSame('Unsplash', $document->source());
        $this->assertTrue($document->isAiGenerated());
    }

    public function test_legacy_plain_filename_photos_still_load(): void
    {
        $recipe = Recipe::factory()->create();
        Storage::disk('recipes')->put($recipe->getKey().'/legacy.jpg', 'binary');

        \DB::table('recipes')->where('id', $recipe->id)->update([
            'photos' => json_encode(['legacy.jpg']),
        ]);

        $document = $recipe->refresh()->photos->first();

        $this->assertSame('legacy.jpg', $document->name());
        $this->assertNull($document->source());
        $this->assertFalse($document->isAiGenerated());
    }

    public function test_editing_a_recipe_prefills_photo_metadata_for_the_form(): void
    {
        $admin = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $admin->author_id]);
        Storage::disk('recipes')->put($recipe->getKey().'/photo.jpg', 'binary');
        $recipe->photos = [[
            'path' => 'photo.jpg',
            'source' => 'Own photo',
            'is_ai_generated' => false,
        ]];
        $recipe->save();

        $this->actingAs($admin);

        /** @var EditRecipe $instance */
        $instance = Livewire::test(EditRecipe::class, ['record' => $recipe->getKey()])->instance();

        /** @var array<string, mixed> $rawState */
        $rawState = $instance->form->getRawState();
        $state = $rawState['photos'];

        $this->assertSame('Own photo', array_values($state)[0]['source']);
        $this->assertFalse(array_values($state)[0]['is_ai_generated']);
    }
}
