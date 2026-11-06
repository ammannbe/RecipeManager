<?php

namespace Tests\Feature;

use App\Ai\AiException;
use App\Ai\RecipeAssistant;
use App\Filament\Pages\ImportRecipe;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipePhotos;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AiRecipeTest extends TestCase
{
    /** A 1×1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

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
        return ['model' => 'test', 'usage' => ['input_tokens' => 120, 'output_tokens' => 80], 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
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
            ->assertSee('Photo or PDF')
            ->fillForm(['ai_file' => [UploadedFile::fake()->image('recipe.jpg')], 'ai_text' => 'Omas Apfelkuchen'])
            ->goToNextWizardStep()
            ->assertHasNoFormErrors()
            ->assertWizardCurrentStep(2);

        // Back and forth with the same inputs costs no second request.
        $component->goToWizardStep(1)->goToNextWizardStep()->assertWizardCurrentStep(2);
        Http::assertSentCount(1);

        $this->assertSame('Apfelkuchen', json_decode((string) $component->get('data.json'), true)['name']);
        $this->assertDatabaseHas('ai_usages', ['feature' => 'recipe', 'input_tokens' => 120, 'output_tokens' => 80]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openai.test/v1/responses'
            && $request['text']['format']['strict'] === true
            && $request['input'][0]['content'][0]['type'] === 'input_image'
            && $request['input'][0]['content'][1]['text'] === 'Omas Apfelkuchen');
    }

    public function test_a_web_address_is_read_and_sent_as_page_text(): void
    {
        $this->fakeDns(['internal.test' => ['10.0.0.5']]);

        Http::fake([
            'rezepte.test/alt' => Http::response('', 301, ['Location' => 'https://rezepte.test/roesti']),
            'rezepte.test/roesti' => Http::response('<html><head><script type="application/ld+json">{"@type":"Recipe","name":"Rösti"}</script></head><body><nav>Menü</nav><h1>Rösti</h1><p>1 kg Kartoffeln</p></body></html>'),
            'openai.test/*' => Http::response($this->answer($this->recipe())),
        ]);
        $this->actingAs(User::factory()->create());

        Livewire::test(ImportRecipe::class)
            ->fillForm(['ai_text' => 'https://rezepte.test/alt'])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(2);

        Http::assertSent(function (Request $request): bool {
            if (! str_ends_with($request->url(), '/responses')) {
                return false;
            }

            $text = $request['input'][0]['content'][0]['text'];

            return str_contains($text, '{"@type":"Recipe","name":"Rösti"}') && str_contains($text, '1 kg Kartoffeln') && ! str_contains($text, 'Menü');
        });

        // Internal addresses are refused, also behind a redirect.
        Http::fake(['rezepte.test/*' => Http::response('', 302, ['Location' => 'http://internal.test/admin'])]);

        Livewire::test(ImportRecipe::class)
            ->fillForm(['ai_text' => 'https://rezepte.test/link'])
            ->goToNextWizardStep()
            ->assertHasFormErrors(['ai_text'])
            ->assertSee('Only public web addresses can be read.')
            ->assertWizardCurrentStep(1);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'internal.test'));
    }

    public function test_the_import_page_hides_ai_for_users_without_access(): void
    {
        $this->actingAs(User::factory()->create(['ai_enabled' => false]));

        Livewire::test(ImportRecipe::class)
            ->assertDontSee('Photo or PDF')
            ->assertSee('Paste recipe JSON');
    }

    public function test_requests_are_limited_per_hour(): void
    {
        Http::fake(['openai.test/*' => Http::response($this->answer($this->recipe()))]);
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['Rezept 1', 'Rezept 2'] as $text) {
            Livewire::test(ImportRecipe::class)->fillForm(['ai_text' => $text])->goToNextWizardStep()->assertHasNoFormErrors();
        }

        Livewire::test(ImportRecipe::class)
            ->fillForm(['ai_text' => 'Rezept 3'])
            ->goToNextWizardStep()
            ->assertHasFormErrors(['ai_text'])
            ->assertSee('Too many AI requests. Please try again later.');

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

        $this->assertDatabaseHas('ai_usages', ['feature' => 'image', 'images' => 1]);

        $photo = $recipe->fresh()->photos->sole();
        $this->assertTrue($photo->isAiGenerated());
        $this->assertSame('ChatGPT (OpenAI)', $photo->source());
        Storage::disk('recipes')->assertExists($recipe->getKey().'/'.$photo->name());

        Http::assertSent(fn (Request $request): bool => str_contains($request['prompt'], 'Rösti') && $request['output_format'] === 'jpeg');
    }

    public function test_without_input_the_first_step_asks_for_the_recipe_and_json_still_works(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ImportRecipe::class)
            ->goToNextWizardStep()
            ->assertHasFormErrors(['ai_text'])
            ->fillForm(['json' => RecipeJsonSchema::exampleJson()])
            ->goToNextWizardStep()
            ->assertHasNoFormErrors()
            ->assertWizardCurrentStep(2);

        Http::assertNothingSent();
    }

    public function test_failed_requests_show_an_error(): void
    {
        Http::fake(['openai.test/*' => Http::response(['error' => 'down'], 500)]);
        $this->actingAs(User::factory()->create());

        Livewire::test(ImportRecipe::class)
            ->fillForm(['ai_text' => 'Rezept'])
            ->goToNextWizardStep()
            ->assertHasFormErrors(['ai_text'])
            ->assertSee('The AI request failed.')
            ->assertSet('data.json', null);
    }

    public function test_the_ai_import_writes_swiss_spelling_unless_disabled(): void
    {
        $recipe = $this->recipe();
        $recipe['name'] = 'Süßkartoffel-Gratin';
        $recipe['instructions'] = '1. Große Süßkartoffeln schälen.';
        $recipe['ingredient_groups'][0]['ingredients'][0]['food'] = 'Weißwein';
        Http::fake(['openai.test/*' => Http::response($this->answer($recipe))]);
        $this->actingAs(User::factory()->create());

        $json = (string) Livewire::test(ImportRecipe::class)
            ->fillForm(['ai_text' => 'Süßkartoffel-Gratin'])
            ->goToNextWizardStep()
            ->get('data.json');

        $this->assertStringNotContainsString('ß', $json);
        $this->assertSame('Süsskartoffel-Gratin', json_decode($json, true)['name']);
        $this->assertSame('1. Grosse Süsskartoffeln schälen.', json_decode($json, true)['instructions']);
        $this->assertSame('Weisswein', json_decode($json, true)['ingredient_groups'][0]['ingredients'][0]['food']);
        Http::assertSent(fn (Request $request): bool => str_contains($request['instructions'] ?? '', 'Schweizer Rechtschreibung'));

        config(['app.recipe_spelling' => 'de_DE']);

        $json = (string) Livewire::test(ImportRecipe::class)
            ->fillForm(['ai_text' => 'Süßkartoffel-Gratin, deutsch'])
            ->goToNextWizardStep()
            ->get('data.json');

        $this->assertSame('Süßkartoffel-Gratin', json_decode($json, true)['name']);
    }

    /**
     * A recipe of the admin with one stored PNG photo.
     */
    private function recipeWithPhoto(User $user): Recipe
    {
        Storage::fake('recipes');
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null, 'name' => 'Rösti', 'photos' => [
            ['path' => 'roesti.png', 'source' => 'Anna', 'is_ai_generated' => false],
        ]]);
        Storage::disk('recipes')->put($recipe->id.'/roesti.png', (string) base64_decode(self::PNG));

        return $recipe;
    }

    private function enhanceAction(string $item): TestAction
    {
        return TestAction::make('enhancePhoto')->arguments(['item' => $item])->schemaComponent('photos');
    }

    public function test_a_photo_is_enhanced_as_an_additional_photo(): void
    {
        Http::fake(['openai.test/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode('edited-jpeg')]], 'usage' => ['input_tokens' => 300, 'output_tokens' => 1000]])]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $recipe = $this->recipeWithPhoto($user);

        $component = Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()]);
        $item = (string) array_key_first($component->get('data.photos'));

        $component
            ->assertActionVisible($this->enhanceAction($item))
            ->callAction($this->enhanceAction($item), data: ['instruction' => 'Etwas wärmeres Licht.'])
            ->assertNotified('Enhanced photo added');

        $photos = $recipe->fresh()->photos->values();
        $this->assertCount(2, $photos);
        // The original stays first and unchanged.
        $this->assertSame('roesti.png', $photos[0]->name());
        $this->assertFalse($photos[0]->isAiGenerated());
        Storage::disk('recipes')->assertExists($recipe->id.'/roesti.png');

        $this->assertTrue($photos[1]->isAiGenerated());
        $this->assertSame('ChatGPT (OpenAI)', $photos[1]->source());
        $this->assertStringEndsWith('.jpg', $photos[1]->name());
        $this->assertSame('edited-jpeg', Storage::disk('recipes')->get($recipe->id.'/'.$photos[1]->name()));
        $this->assertDatabaseHas('ai_usages', ['user_id' => $user->id, 'feature' => 'image_edit', 'images' => 1, 'input_tokens' => 300]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openai.test/v1/images/edits'
            && $request->isMultipart()
            && collect($request->data())->contains(fn (array $part): bool => $part['name'] === 'image' && $part['contents'] === base64_decode(self::PNG))
            && collect($request->data())->contains(fn (array $part): bool => $part['name'] === 'prompt' && str_contains((string) $part['contents'], 'Etwas wärmeres Licht.'))
            && collect($request->data())->contains(fn (array $part): bool => $part['name'] === 'model' && $part['contents'] === 'gpt-image-2'));
    }

    public function test_without_an_instruction_the_photo_is_only_retouched(): void
    {
        Http::fake(['openai.test/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode('edited-jpeg')]]])]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $recipe = $this->recipeWithPhoto($user);

        $component = Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()]);
        $component->callAction($this->enhanceAction((string) array_key_first($component->get('data.photos'))));

        Http::assertSent(fn (Request $request): bool => collect($request->data())->contains(
            fn (array $part): bool => $part['name'] === 'prompt' && str_contains((string) $part['contents'], RecipeAssistant::DEFAULT_ENHANCE_INSTRUCTION),
        ));
    }

    public function test_photos_are_neither_enhanced_nor_created_beyond_the_limit(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->actingAs($user);
        $recipe = $this->recipeWithPhoto($user);
        $recipe->update(['photos' => array_fill(0, RecipeJsonSchema::MAX_PHOTOS, ['path' => 'roesti.png', 'source' => null, 'is_ai_generated' => false])]);

        $component = Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()]);
        $component->callAction($this->enhanceAction((string) array_key_first($component->get('data.photos'))))
            ->assertNotified(RecipePhotos::FULL);
        $component->callAction('aiPhoto')->assertNotified(RecipePhotos::FULL);

        Http::assertNothingSent();
        $this->assertCount(RecipeJsonSchema::MAX_PHOTOS, $recipe->fresh()->photos);
        $this->assertDatabaseCount('ai_usages', 0);
    }

    public function test_enhancing_needs_ai_access_and_edit_rights(): void
    {
        $user = User::factory()->create(['ai_enabled' => false]);
        $this->actingAs($user);
        $recipe = $this->recipeWithPhoto($user);

        $component = Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()]);
        $component->assertActionHidden($this->enhanceAction((string) array_key_first($component->get('data.photos'))));

        // The service checks the rights itself, too.
        $this->expectException(AiException::class);

        try {
            app(RecipeAssistant::class)->enhance($user, $recipe, 'roesti.png');
        } finally {
            Http::assertNothingSent();
            $this->assertCount(1, $recipe->fresh()->photos);
        }
    }

    public function test_photos_of_recipes_the_user_may_not_change_cannot_be_enhanced(): void
    {
        $owner = User::factory()->create();
        $recipe = $this->recipeWithPhoto($owner);
        $recipe->update(['is_public' => true]);

        $this->actingAs(User::factory()->create());
        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])->assertNotFound();

        // Trashed recipes are read-only.
        $this->actingAs($owner);
        $recipe->delete();
        $component = Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()]);
        $component->assertActionHidden($this->enhanceAction((string) array_key_first($component->get('data.photos'))));
    }
}
