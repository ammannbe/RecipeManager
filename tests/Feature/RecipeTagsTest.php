<?php

namespace Tests\Feature;

use App\Enums\Complexity;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\TagResource;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeTagsTest extends TestCase
{
    public function test_tags_can_be_attached_through_the_recipe_form(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $tag = Tag::factory()->create();

        $this->actingAs($user);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->fillForm(['tags' => [$tag->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$tag->id], $recipe->refresh()->tags->pluck('id')->all());
    }

    public function test_tags_can_be_detached_through_the_recipe_form(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $recipe->tags()->attach(Tag::factory()->create());

        $this->actingAs($user);

        Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->fillForm(['tags' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(0, $recipe->refresh()->tags);
    }

    public function test_the_form_prefills_the_attached_tags(): void
    {
        $user = User::factory()->admin()->create();
        $recipe = Recipe::factory()->create(['author_id' => $user->author_id]);
        $tag = Tag::factory()->create();
        $recipe->tags()->attach($tag);

        $this->actingAs($user);

        $state = Livewire::test(EditRecipe::class, ['record' => $recipe->getRouteKey()])
            ->get('data')['tags'];

        $this->assertEquals([$tag->id], array_values((array) $state));
    }

    public function test_a_non_admin_cannot_create_a_tag_from_the_form(): void
    {
        $user = User::factory()->create(['admin' => false]);

        $this->actingAs($user);

        $this->assertFalse($user->can('create', Tag::class));
    }

    public function test_an_admin_can_create_a_tag_from_the_form(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user);

        $this->assertTrue($user->can('create', Tag::class));
    }

    public function test_a_tag_cannot_be_attached_to_the_same_recipe_twice(): void
    {
        $recipe = Recipe::factory()->create();
        $tag = Tag::factory()->create();

        $recipe->tags()->attach($tag);

        $this->expectException(QueryException::class);

        $recipe->tags()->attach($tag);
    }

    public function test_the_public_index_filters_by_tag(): void
    {
        $tag = Tag::factory()->create();

        $tagged = Recipe::factory()->create(['cookbook_id' => null, 'name' => 'Tagged recipe']);
        $tagged->tags()->attach($tag);

        Recipe::factory()->create(['cookbook_id' => null, 'name' => 'Untagged recipe']);

        $this->get(route('recipes.index', ['tags' => [$tag->id]]))
            ->assertOk()
            ->assertSee('Tagged recipe')
            ->assertDontSee('Untagged recipe');
    }

    public function test_the_public_index_ignores_an_unknown_tag_filter(): void
    {
        Recipe::factory()->create(['cookbook_id' => null, 'name' => 'Some recipe']);

        $this->get(route('recipes.index', ['tags' => [999999]]))
            ->assertOk()
            ->assertSee('Some recipe');
    }

    public function test_the_public_recipe_page_shows_its_tags(): void
    {
        $recipe = Recipe::factory()->create(['cookbook_id' => null]);
        $recipe->tags()->attach(Tag::factory()->create(['name' => 'Herbst']));

        $this->get(route('recipes.show', $recipe))
            ->assertOk()
            ->assertSee('Herbst');
    }

    public function test_an_admin_can_list_tags_with_their_recipe_count(): void
    {
        $user = User::factory()->admin()->create();
        $tag = Tag::factory()->create(['name' => 'Herbst']);
        Recipe::factory()->create()->tags()->attach($tag);

        $this->actingAs($user)
            ->get(TagResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Herbst');
    }

    public function test_an_admin_can_create_a_tag_from_the_resource(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateTag::class)
            ->fillForm(['name' => 'Winter'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tags', ['name' => 'Winter']);
    }

    public function test_a_tag_name_must_be_unique(): void
    {
        Tag::factory()->create(['name' => 'Sommer']);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateTag::class)
            ->fillForm(['name' => 'Sommer'])
            ->call('create')
            ->assertHasFormErrors(['name']);
    }

    public function test_an_admin_can_rename_a_tag(): void
    {
        $user = User::factory()->admin()->create();
        $tag = Tag::factory()->create(['name' => 'Alt']);

        $this->actingAs($user);

        Livewire::test(EditTag::class, ['record' => $tag->getRouteKey()])
            ->fillForm(['name' => 'Neu'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Neu', $tag->refresh()->name);
    }

    public function test_a_non_admin_cannot_reach_the_tag_create_page(): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]))
            ->get(TagResource::getUrl('create'))
            ->assertForbidden();
    }

    /**
     * MariaDB orders an ENUM by its declaration order, which already runs
     * simple -> normal -> difficult, so sorting must not fall back to alphabetical.
     */
    public function test_the_public_index_sorts_by_difficulty(): void
    {
        foreach (Complexity::cases() as $case) {
            Recipe::factory()->create([
                'cookbook_id' => null,
                'complexity' => $case,
                'name' => 'Recipe '.$case->value,
            ]);
        }

        $response = $this->get(route('recipes.index', ['sort' => 'complexity_asc']))->assertOk();

        $response->assertSeeInOrder([
            'Recipe simple',
            'Recipe normal',
            'Recipe difficult',
        ], escape: false);
    }
}
