<?php

namespace Tests\Feature;

use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Filament\Resources\Recipes\Schemas\ListItemParagraphs;
use App\Models\Recipe;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeEditPageTest extends TestCase
{
    public function test_the_panel_warns_about_unsaved_changes_and_the_page_marks_them(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        $recipe = Recipe::factory()->create(['cookbook_id' => null, 'name' => 'Rösti']);

        $this->assertTrue(Filament::getPanel('app')->hasUnsavedChangesAlerts());

        $this->get(RecipeResource::getUrl('edit', ['record' => $recipe]))
            ->assertSuccessful()
            ->assertSee('setUpFilamentUnsavedDataChangesAlert', false)
            // An info box apart from the title and a blue bar beside changed fields, not in the heading.
            ->assertSee('data-unsaved-changes', false)
            ->assertSee('data-unsaved-field', false)
            ->assertSee('var(--info-500)', false)
            ->assertDontSee('data-unsaved-indicator', false);

        // Only on the recipe edit page.
        $this->get(RecipeResource::getUrl('create'))->assertSuccessful()->assertDontSee('data-unsaved-changes', false);
    }

    public function test_a_freshly_loaded_recipe_with_a_tight_list_is_not_changed_by_the_editor(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        // Like the import writes it: list items without paragraphs.
        $recipe = Recipe::factory()->create(['cookbook_id' => null, 'instructions' => Str::markdown("1. Mehl sieben.\n2. Butter dazu.\n\nFertig.")]);

        $component = Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()]);
        $instructions = $component->get('data.instructions');

        // The editor's JavaScript would wrap the text in a paragraph on load (normalize-list-item-content.js)
        // and the form would differ from the saved hash before any change; the state already has it.
        $item = $instructions['content'][0]['content'][0];
        $this->assertSame('listItem', $item['type']);
        $this->assertSame([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Mehl sieben.']]]], $item['content']);
        $this->assertSame(md5((string) str((string) json_encode($component->get('data'), JSON_UNESCAPED_UNICODE))->replace('\\', '')), $component->get('savedDataHash'));

        $component->call('save')->assertHasNoErrors();
        $this->assertStringContainsString('Mehl sieben.', (string) $recipe->fresh()->instructions);
        $this->assertStringContainsString('<ol>', (string) $recipe->fresh()->instructions);
    }

    public function test_list_items_are_normalized_like_the_editor_does(): void
    {
        $text = ['type' => 'text', 'text' => 'A'];
        $list = ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [$text]]]];

        $this->assertSame([
            'type' => 'doc',
            'content' => [[
                'type' => 'orderedList',
                'attrs' => ['start' => 1],
                'content' => [
                    // Inline text, then a nested list: a paragraph for the text, the list stays.
                    ['type' => 'listItem', 'content' => [
                        ['type' => 'paragraph', 'content' => [$text]],
                        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text]]]]]],
                    ]],
                    // Starting with a block: an empty paragraph first.
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => []], ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text]]]]]]]],
                    // Already a paragraph: unchanged.
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text]]]],
                ],
            ]],
        ], ListItemParagraphs::normalize([
            'type' => 'doc',
            'content' => [[
                'type' => 'orderedList',
                'attrs' => ['start' => 1],
                'content' => [
                    ['type' => 'listItem', 'content' => [$text, $list]],
                    ['type' => 'listItem', 'content' => [$list]],
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text]]]],
                ],
            ]],
        ]));
    }

    public function test_add_buttons_quote_the_name(): void
    {
        app()->setLocale('de');
        $this->assertSame('Zu «Zutaten» hinzufügen', __('filament-forms::components.repeater.actions.add.label', ['label' => 'Zutaten']));
        $this->assertSame('Zu «Zutaten» hinzufügen', __('filament-forms::components.builder.actions.add.label', ['label' => 'Zutaten']));
        // The other translations of the package stay.
        $this->assertSame('Bitte wählen', __('filament-forms::components.select.placeholder'));
        $this->assertSame('Verschieben', __('filament-forms::components.repeater.actions.reorder.label'));

        app()->setLocale('en');
        $this->assertSame('Add to "Ingredients"', __('filament-forms::components.repeater.actions.add.label', ['label' => 'Ingredients']));
    }

    public function test_the_edit_page_shows_the_quoted_add_buttons(): void
    {
        $user = User::factory()->admin()->create(['locale' => 'de']);
        $this->actingAs($user);
        $recipe = Recipe::factory()->create(['cookbook_id' => null]);

        $this->get(RecipeResource::getUrl('edit', ['record' => $recipe]))
            ->assertSuccessful()
            ->assertSee('Zu «', false)
            ->assertDontSee('Zu Zutaten hinzufügen');
    }
}
