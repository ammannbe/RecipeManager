<?php

namespace Tests\Feature;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecipeVisibilityTest extends TestCase
{
    private function photoOn(Recipe $recipe, string $filename = 'photo.jpg'): void
    {
        Storage::disk('recipes')->put($recipe->getKey().'/'.$filename, 'binary');

        $recipe->photos = [$filename];
        $recipe->save();
    }

    public function test_a_guest_sees_public_recipes_in_the_list(): void
    {
        Recipe::factory()->create(['is_public' => true, 'name' => 'Public recipe']);
        Recipe::factory()->create(['is_public' => false, 'name' => 'Private recipe']);

        $this->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('Public recipe')
            ->assertDontSee('Private recipe');
    }

    public function test_a_guest_cannot_open_a_private_recipe(): void
    {
        $recipe = Recipe::factory()->create(['is_public' => false]);

        $this->get(route('recipes.show', $recipe))->assertNotFound();
    }

    public function test_a_guest_can_open_a_public_recipe(): void
    {
        $recipe = Recipe::factory()->create(['is_public' => true]);

        $this->get(route('recipes.show', $recipe))->assertOk();
    }

    public function test_an_author_sees_their_own_private_recipe(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $recipe = Recipe::factory()->create([
            'is_public' => false,
            'author_id' => $user->author_id,
            'name' => 'My private recipe',
        ]);

        $this->actingAs($user)
            ->get(route('recipes.show', $recipe))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('My private recipe');
    }

    public function test_another_author_cannot_open_a_private_recipe(): void
    {
        $recipe = Recipe::factory()->create(['is_public' => false]);

        $this->actingAs(User::factory()->create(['admin' => false]))
            ->get(route('recipes.show', $recipe))
            ->assertNotFound();
    }

    public function test_an_admin_can_open_any_private_recipe(): void
    {
        $recipe = Recipe::factory()->create(['is_public' => false]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('recipes.show', $recipe))
            ->assertOk();
    }

    /**
     * Visibility now follows the flag alone, no longer "has no cookbook".
     */
    public function test_a_recipe_inside_a_cookbook_can_still_be_public(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $user->author_id]);

        $recipe = Recipe::factory()->create([
            'is_public' => true,
            'author_id' => $user->author_id,
            'cookbook_id' => $cookbook->id,
        ]);

        $this->get(route('recipes.show', $recipe))->assertOk();
    }

    public function test_a_recipe_without_a_cookbook_can_still_be_private(): void
    {
        $recipe = Recipe::factory()->create(['is_public' => false, 'cookbook_id' => null]);

        $this->get(route('recipes.show', $recipe))->assertNotFound();
    }

    public function test_the_index_can_be_filtered_by_cookbook(): void
    {
        $cookbook = Cookbook::factory()->create(['is_public' => true]);
        $other = Cookbook::factory()->create(['is_public' => true]);

        Recipe::factory()->create(['is_public' => true, 'cookbook_id' => $cookbook->id, 'name' => 'In cookbook']);
        Recipe::factory()->create(['is_public' => true, 'cookbook_id' => $other->id, 'name' => 'In other cookbook']);

        $this->get(route('recipes.index', ['cookbook' => $cookbook->id]))
            ->assertOk()
            ->assertSee('In cookbook')
            ->assertDontSee('In other cookbook');
    }

    public function test_the_cookbook_filter_lists_owned_and_public_cookbooks_for_a_user(): void
    {
        $user = User::factory()->create(['admin' => false]);
        Cookbook::factory()->create(['author_id' => $user->author_id, 'is_public' => false, 'name' => 'Owned cookbook']);
        Cookbook::factory()->create(['is_public' => true, 'name' => 'Public cookbook']);
        Cookbook::factory()->create(['is_public' => false, 'name' => 'Private cookbook']);

        $this->actingAs($user)
            ->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('Owned cookbook')
            ->assertSee('Public cookbook')
            ->assertDontSee('Private cookbook');
    }

    public function test_the_cookbook_filter_only_lists_public_cookbooks_for_a_guest(): void
    {
        Cookbook::factory()->create(['is_public' => true, 'name' => 'Public cookbook']);
        Cookbook::factory()->create(['is_public' => false, 'name' => 'Private cookbook']);

        $this->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('Public cookbook')
            ->assertDontSee('Private cookbook');
    }

    public function test_a_guest_cannot_load_a_private_recipe_photo(): void
    {
        Storage::fake('recipes');

        $recipe = Recipe::factory()->create(['is_public' => false]);
        $this->photoOn($recipe);

        $this->get(route('recipes.photo', ['recipe' => $recipe, 'filename' => 'photo.jpg']))
            ->assertNotFound();
    }

    public function test_a_guest_can_load_a_public_recipe_photo(): void
    {
        Storage::fake('recipes');

        $recipe = Recipe::factory()->create(['is_public' => true]);
        $this->photoOn($recipe);

        $this->get(route('recipes.photo', ['recipe' => $recipe, 'filename' => 'photo.jpg']))
            ->assertOk();
    }

    public function test_the_owner_can_load_their_private_recipe_photo(): void
    {
        Storage::fake('recipes');

        $user = User::factory()->create(['admin' => false]);
        $recipe = Recipe::factory()->create(['is_public' => false, 'author_id' => $user->author_id]);
        $this->photoOn($recipe);

        $this->actingAs($user)
            ->get(route('recipes.photo', ['recipe' => $recipe, 'filename' => 'photo.jpg']))
            ->assertOk();
    }

    public function test_an_unknown_filename_is_rejected(): void
    {
        Storage::fake('recipes');

        $recipe = Recipe::factory()->create(['is_public' => true]);
        $this->photoOn($recipe);

        $this->get(route('recipes.photo', ['recipe' => $recipe, 'filename' => 'other.jpg']))
            ->assertNotFound();
    }

    /**
     * The filename must be matched against the recipe's own list, never used as a path.
     */
    public function test_a_photo_of_another_recipe_cannot_be_borrowed(): void
    {
        Storage::fake('recipes');

        $private = Recipe::factory()->create(['is_public' => false]);
        $this->photoOn($private, 'secret.jpg');

        $public = Recipe::factory()->create(['is_public' => true]);
        $this->photoOn($public, 'shown.jpg');

        $this->get(route('recipes.photo', ['recipe' => $public, 'filename' => 'secret.jpg']))
            ->assertNotFound();
    }

    public function test_no_photos_are_left_in_the_public_directory(): void
    {
        $public = storage_path('app/public/recipes');

        // An empty leftover directory is harmless; actual files there would be served
        // by the web server without any authorization.
        $this->assertSame(
            [],
            File::isDirectory($public) ? File::allFiles($public) : [],
        );
    }

    public function test_the_photo_disk_is_private(): void
    {
        $this->assertSame(
            storage_path('app/private/recipes'),
            config('filesystems.disks.recipes.root'),
        );
        $this->assertNull(config('filesystems.disks.recipes.url'));
    }

    public function test_the_photo_url_points_at_the_authorized_route(): void
    {
        Storage::fake('recipes');

        $recipe = Recipe::factory()->create(['is_public' => true]);
        $this->photoOn($recipe);

        $url = $recipe->refresh()->photos->first()?->url();

        $this->assertSame(
            route('recipes.photo', ['recipe' => $recipe->getKey(), 'filename' => 'photo.jpg']),
            $url,
        );
        $this->assertStringNotContainsString('/storage/', (string) $url);
    }
}
