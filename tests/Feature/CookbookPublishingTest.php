<?php

namespace Tests\Feature;

use App\Filament\Resources\Cookbooks\Pages\CreateCookbook;
use App\Filament\Resources\Cookbooks\Pages\EditCookbook;
use App\Models\Author;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CookbookPublishingTest extends TestCase
{
    public function test_a_non_admin_cannot_create_a_cookbook_for_another_author(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $victim = Author::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateCookbook::class)
            ->fillForm(['name' => 'Spoofed', 'author_id' => $victim->id])
            ->call('create');

        $cookbook = Cookbook::query()->where('name', 'Spoofed')->firstOrFail();

        $this->assertSame($user->author_id, $cookbook->author_id);
        $this->assertNotSame($victim->id, $cookbook->author_id);
    }

    public function test_a_non_admin_cannot_reassign_their_cookbook(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $victim = Author::factory()->create();
        $cookbook = Cookbook::factory()->create(['author_id' => $user->author_id]);

        $this->actingAs($user);

        Livewire::test(EditCookbook::class, ['record' => $cookbook->getRouteKey()])
            ->fillForm(['name' => 'Renamed', 'author_id' => $victim->id])
            ->call('save');

        $this->assertSame($user->author_id, $cookbook->refresh()->author_id);
    }

    public function test_an_admin_can_choose_the_author(): void
    {
        $admin = User::factory()->admin()->create();
        $other = Author::factory()->create();

        $this->actingAs($admin);

        Livewire::test(CreateCookbook::class)
            ->fillForm(['name' => 'Assigned', 'author_id' => $other->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($other->id, Cookbook::query()->where('name', 'Assigned')->firstOrFail()->author_id);
    }

    public function test_a_cookbook_name_must_be_unique_per_author(): void
    {
        $author = Author::factory()->create();
        Cookbook::factory()->create(['author_id' => $author->id, 'name' => 'Backbuch']);

        $this->expectException(QueryException::class);

        Cookbook::factory()->create(['author_id' => $author->id, 'name' => 'Backbuch']);
    }

    public function test_two_authors_may_use_the_same_cookbook_name(): void
    {
        $first = Cookbook::factory()->create(['name' => 'Backbuch']);
        $second = Cookbook::factory()->create(['name' => 'Backbuch']);

        $this->assertNotSame($first->author_id, $second->author_id);
        $this->assertSame(2, Cookbook::query()->where('name', 'Backbuch')->count());
    }

    public function test_publishing_a_cookbook_exposes_its_recipes(): void
    {
        $cookbook = Cookbook::factory()->create(['is_public' => false]);
        $recipe = Recipe::factory()->create([
            'cookbook_id' => $cookbook->id,
            'author_id' => $cookbook->author_id,
            'is_public' => false,
            'name' => 'Hidden recipe',
        ]);

        $this->get(route('recipes.show', $recipe))->assertNotFound();

        $cookbook->update(['is_public' => true]);

        $this->get(route('recipes.show', $recipe->refresh()))->assertOk();
        $this->get(route('recipes.index'))->assertOk()->assertSee('Hidden recipe');
    }

    public function test_unpublishing_a_cookbook_hides_its_recipes_again(): void
    {
        $cookbook = Cookbook::factory()->create(['is_public' => true]);
        $recipe = Recipe::factory()->create([
            'cookbook_id' => $cookbook->id,
            'author_id' => $cookbook->author_id,
            'is_public' => false,
            'name' => 'Temporarily public',
        ]);

        $this->get(route('recipes.show', $recipe))->assertOk();

        $cookbook->update(['is_public' => false]);

        $this->get(route('recipes.show', $recipe->refresh()))->assertNotFound();
        $this->get(route('recipes.index'))->assertOk()->assertDontSee('Temporarily public');
    }

    /**
     * Either flag alone is enough, so a public recipe stays public in a private cookbook.
     */
    public function test_a_public_recipe_stays_public_inside_a_private_cookbook(): void
    {
        $cookbook = Cookbook::factory()->create(['is_public' => false]);
        $recipe = Recipe::factory()->create([
            'cookbook_id' => $cookbook->id,
            'author_id' => $cookbook->author_id,
            'is_public' => true,
        ]);

        $this->get(route('recipes.show', $recipe))->assertOk();
    }

    public function test_a_published_cookbook_also_exposes_recipe_photos(): void
    {
        Storage::fake('recipes');

        $cookbook = Cookbook::factory()->create(['is_public' => true]);
        $recipe = Recipe::factory()->create([
            'cookbook_id' => $cookbook->id,
            'author_id' => $cookbook->author_id,
            'is_public' => false,
        ]);

        Storage::disk('recipes')->put($recipe->getKey().'/pic.jpg', 'binary');
        $recipe->photos = ['pic.jpg'];
        $recipe->save();

        $this->get(route('recipes.photo', ['recipe' => $recipe, 'filename' => 'pic.jpg']))
            ->assertOk();
    }

    public function test_the_cookbook_list_only_shows_own_cookbooks_to_non_admins(): void
    {
        $user = User::factory()->create(['admin' => false]);
        Cookbook::factory()->create(['author_id' => $user->author_id, 'name' => 'Mine']);
        Cookbook::factory()->create(['name' => 'Theirs']);

        $this->actingAs($user)
            ->get('/admin/cookbooks')
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Theirs');
    }
}
