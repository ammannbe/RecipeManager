<?php

namespace Tests\Feature;

use App\Ai\RecipeAssistant;
use App\Filament\Pages\ImportRecipe;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeImport\RecipeJsonSchema;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AiRecipeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'test-key',
            'services.openai.url' => 'https://openai.test/v1',
            'services.openai.hourly_limit' => 2,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function answer(array $data): array
    {
        return ['model' => 'test', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
    }

    /**
     * The example recipe in the strict AI shape (every field present).
     *
     * @return array<string, mixed>
     */
    private function recipe(): array
    {
        $recipe = RecipeJsonSchema::example();
        $recipe['ingredient_groups'][0]['ingredients'][1]['alternatives'][0] += ['alternatives' => []];

        return $recipe;
    }

    public function test_only_verified_users_with_ai_enabled_may_use_it(): void
    {
        $this->assertTrue(RecipeAssistant::allows(User::factory()->create()));
        $this->assertFalse(RecipeAssistant::allows(User::factory()->create(['ai_enabled' => false])));
        $this->assertFalse(RecipeAssistant::allows(User::factory()->unverified()->create()));
        $this->assertFalse(RecipeAssistant::allows(null));

        config(['services.openai.key' => null]);
        $this->assertFalse(RecipeAssistant::allows(User::factory()->create()));
    }

    public function test_the_import_page_creates_the_json_from_a_photo(): void
    {
        Http::fake(['openai.test/*' => Http::response($this->answer($this->recipe()))]);
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(ImportRecipe::class)
            ->assertSee('Create with AI')
            ->fillForm(['ai_file' => [UploadedFile::fake()->image('recipe.jpg')], 'ai_text' => 'Omas Apfelkuchen'])
            ->call('createWithAi')
            ->assertNotified('Recipe recognized');

        $this->assertSame('Apfelkuchen', json_decode((string) $component->get('data.json'), true)['name']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openai.test/v1/responses'
            && $request['text']['format']['strict'] === true
            && $request['input'][0]['content'][0]['type'] === 'input_image'
            && $request['input'][0]['content'][1]['text'] === 'Omas Apfelkuchen');
    }

    public function test_the_import_page_hides_ai_for_users_without_access(): void
    {
        $this->actingAs(User::factory()->create(['ai_enabled' => false]));

        Livewire::test(ImportRecipe::class)->assertDontSee('Create with AI');
    }

    public function test_requests_are_limited_per_hour(): void
    {
        Http::fake(['openai.test/*' => Http::response($this->answer($this->recipe()))]);
        $user = User::factory()->create();
        $this->actingAs($user);

        $page = Livewire::test(ImportRecipe::class)->fillForm(['ai_text' => 'Rezept']);
        $page->call('createWithAi')->assertNotified('Recipe recognized');
        $page->call('createWithAi')->assertNotified('Recipe recognized');
        $page->call('createWithAi')->assertNotified('Too many AI requests. Please try again later.');

        Http::assertSentCount(2);
    }

    public function test_a_photo_is_created_for_a_recipe(): void
    {
        Storage::fake('recipes');
        Http::fake(['openai.test/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('jpeg-bytes')]]])]);

        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        $recipe = Recipe::factory()->create(['cookbook_id' => null, 'name' => 'Rösti', 'photos' => []]);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->callAction('aiPhoto')
            ->assertNotified('Photo created');

        $photo = $recipe->fresh()->photos->sole();
        $this->assertTrue($photo->isAiGenerated());
        Storage::disk('recipes')->assertExists($recipe->getKey().'/'.$photo->name());

        Http::assertSent(fn (Request $request): bool => str_contains($request['prompt'], 'Rösti') && $request['output_format'] === 'jpeg');
    }

    public function test_failed_requests_show_an_error(): void
    {
        Http::fake(['openai.test/*' => Http::response(['error' => 'down'], 500)]);
        $this->actingAs(User::factory()->create());

        Livewire::test(ImportRecipe::class)
            ->fillForm(['ai_text' => 'Rezept'])
            ->call('createWithAi')
            ->assertNotified('The AI request failed.')
            ->assertSet('data.json', null);
    }
}
