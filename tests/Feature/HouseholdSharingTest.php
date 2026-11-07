<?php

namespace Tests\Feature;

use App\Enums\HouseholdRole;
use App\Filament\Resources\Cookbooks\Pages\EditCookbook;
use App\Filament\Resources\Cookbooks\RelationManagers\HouseholdsRelationManager;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Models\Cookbook;
use App\Models\Household;
use App\Models\Recipe;
use App\Models\User;
use App\Services\CookbookSharing;
use App\Services\Households;
use Filament\Actions\Testing\TestAction;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cookbooks shared with a household: access is resolved through the membership.
 */
class HouseholdSharingTest extends TestCase
{
    private User $owner;

    private User $member;

    private Household $household;

    private Cookbook $cookbook;

    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['admin' => false]);
        $this->member = User::factory()->create(['admin' => false]);

        $this->household = Household::factory()
            ->administeredBy($this->owner)
            ->withMember($this->member)
            ->create();

        $this->cookbook = Cookbook::factory()->create(['author_id' => $this->owner->author_id, 'is_public' => false]);
        $this->recipe = Recipe::factory()->create([
            'cookbook_id' => $this->cookbook->id,
            'author_id' => $this->owner->author_id,
            'is_public' => false,
            'name' => 'Household soup',
        ]);
    }

    /**
     * @param  array<string, bool>  $grants
     */
    private function share(array $grants): void
    {
        $this->assertTrue(app(CookbookSharing::class)->shareWithHousehold($this->cookbook, $this->household, $grants, $this->owner));
    }

    public function test_a_member_reads_a_shared_cookbook_and_its_recipes(): void
    {
        $this->share(['can_read' => true]);

        $this->assertTrue($this->member->can('view', $this->cookbook));
        $this->assertTrue($this->member->can('view', $this->recipe));
        $this->assertFalse($this->member->can('update', $this->recipe));
        $this->assertFalse($this->member->can('delete', $this->recipe));
        $this->assertFalse($this->member->can('share', $this->cookbook));

        $this->assertTrue(Recipe::query()->visibleTo($this->member)->whereKey($this->recipe->id)->exists());
        $this->assertTrue(Cookbook::query()->visibleTo($this->member)->whereKey($this->cookbook->id)->exists());
        $this->assertFalse(Cookbook::query()->administeredBy($this->member)->whereKey($this->cookbook->id)->exists());

        $this->actingAs($this->member)
            ->get(route('recipes.show', $this->recipe))
            ->assertOk();

        $this->actingAs($this->member)
            ->get(route('recipes.index'))
            ->assertSee('Household soup');
    }

    public function test_the_share_grants_apply_to_members(): void
    {
        $this->share(['can_update' => true]);

        $this->assertTrue($this->member->can('view', $this->recipe));
        $this->assertTrue($this->member->can('update', $this->recipe));
        $this->assertFalse($this->member->can('delete', $this->recipe));
    }

    public function test_an_admin_share_lets_members_manage_the_cookbook(): void
    {
        $this->share(['can_admin' => true]);

        $this->assertTrue($this->member->can('share', $this->cookbook));
        $this->assertTrue($this->member->can('delete', $this->recipe));
        $this->assertTrue(Cookbook::query()->administeredBy($this->member)->whereKey($this->cookbook->id)->exists());
    }

    public function test_a_share_without_read_grants_no_access(): void
    {
        $this->cookbook->households()->attach($this->household->id, ['can_read' => false]);

        $this->assertFalse($this->member->can('view', $this->recipe));
        $this->assertFalse(Recipe::query()->visibleTo($this->member)->whereKey($this->recipe->id)->exists());
    }

    public function test_a_non_member_gets_nothing(): void
    {
        $this->share(['can_admin' => true]);
        $stranger = User::factory()->create(['admin' => false]);

        $this->assertFalse($stranger->can('view', $this->cookbook));
        $this->assertFalse($stranger->can('view', $this->recipe));
        $this->assertFalse(Recipe::query()->visibleTo($stranger)->whereKey($this->recipe->id)->exists());
        $this->assertFalse(Cookbook::query()->administeredBy($stranger)->whereKey($this->cookbook->id)->exists());

        $this->actingAs($stranger)
            ->get(route('recipes.show', $this->recipe))
            ->assertNotFound();
    }

    public function test_a_new_member_gets_access_without_own_share(): void
    {
        $this->share(['can_read' => true]);
        $newcomer = User::factory()->create(['admin' => false]);

        $this->assertFalse($newcomer->can('view', $this->recipe));

        $this->household->users()->attach($newcomer->id, ['role' => HouseholdRole::Member->value, 'joined_at' => now()]);

        $this->assertTrue($newcomer->can('view', $this->recipe));
    }

    public function test_a_removed_member_loses_access(): void
    {
        $this->share(['can_update' => true]);

        app(Households::class)->removeMember($this->household, $this->member);

        $this->assertFalse($this->member->can('view', $this->recipe));
        $this->assertFalse($this->member->can('update', $this->recipe));
        $this->assertFalse(Recipe::query()->visibleTo($this->member)->whereKey($this->recipe->id)->exists());
        // The content stays with its owner.
        $this->assertTrue($this->owner->can('update', $this->recipe->refresh()));
    }

    public function test_removing_or_leaving_unshares_only_the_members_own_cookbooks(): void
    {
        $this->share(['can_read' => true]);
        $leaving = User::factory()->create(['admin' => false]);
        $this->household->users()->attach($leaving->id, ['role' => HouseholdRole::Member->value, 'joined_at' => now()]);
        $removedOwn = Cookbook::factory()->create(['author_id' => $this->member->author_id, 'is_public' => false]);
        $leavingOwn = Cookbook::factory()->create(['author_id' => $leaving->author_id, 'is_public' => false]);
        $trashed = Cookbook::factory()->create(['author_id' => $leaving->author_id, 'is_public' => false]);
        $elsewhere = Household::factory()->administeredBy($this->member)->create();
        $sharing = app(CookbookSharing::class);
        $this->assertTrue($sharing->shareWithHousehold($removedOwn, $this->household, ['can_read' => true], $this->member));
        $this->assertTrue($sharing->shareWithHousehold($removedOwn, $elsewhere, ['can_read' => true], $this->member));
        $this->assertTrue($sharing->shareWithHousehold($leavingOwn, $this->household, ['can_read' => true], $leaving));
        $this->assertTrue($sharing->shareWithHousehold($trashed, $this->household, ['can_read' => true], $leaving));
        $trashed->delete();

        app(Households::class)->removeMember($this->household, $this->member);

        $this->assertFalse($removedOwn->households()->whereKey($this->household->id)->exists());
        $this->assertFalse($this->owner->can('view', $removedOwn));
        // Other households and the other members' shares stay.
        $this->assertTrue($removedOwn->households()->whereKey($elsewhere->id)->exists());
        $this->assertTrue($leavingOwn->households()->whereKey($this->household->id)->exists());
        $this->assertTrue($this->cookbook->households()->whereKey($this->household->id)->exists());

        app(Households::class)->leave($this->household, $leaving);

        $this->assertDatabaseMissing('cookbook_household', ['household_id' => $this->household->id, 'cookbook_id' => $leavingOwn->id]);
        $this->assertDatabaseMissing('cookbook_household', ['household_id' => $this->household->id, 'cookbook_id' => $trashed->id]);
        $this->assertSame([$this->cookbook->id], $this->household->cookbooks()->pluck('cookbooks.id')->all());
        // The cookbooks stay with their owners.
        $this->assertTrue($this->member->can('update', $removedOwn));
        $this->assertTrue($leaving->can('update', $leavingOwn));
        $this->assertTrue($this->owner->can('view', $this->recipe));
    }

    public function test_deleting_the_household_removes_access_but_keeps_the_content(): void
    {
        $this->share(['can_read' => true]);

        app(Households::class)->delete($this->household);

        $this->assertFalse($this->member->can('view', $this->recipe));
        $this->assertDatabaseMissing('cookbook_household', ['cookbook_id' => $this->cookbook->id]);
        $this->assertDatabaseHas('cookbooks', ['id' => $this->cookbook->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('recipes', ['id' => $this->recipe->id, 'deleted_at' => null]);
    }

    public function test_unsharing_removes_access(): void
    {
        $this->share(['can_read' => true]);

        $this->cookbook->households()->detach($this->household->id);

        $this->assertFalse($this->member->can('view', $this->recipe));
    }

    public function test_personal_and_household_grants_add_up(): void
    {
        $this->share(['can_read' => true]);
        app(CookbookSharing::class)->addMember($this->cookbook, $this->member, ['can_delete' => true]);

        $this->assertTrue($this->member->can('delete', $this->recipe));
        $this->assertFalse($this->member->can('update', $this->recipe));

        // Personal shares keep working without households.
        app(Households::class)->removeMember($this->household, $this->member);
        $this->assertTrue($this->member->can('delete', $this->recipe));
    }

    public function test_only_households_of_the_sharing_user_are_allowed(): void
    {
        $foreign = Household::factory()->create();

        $this->assertFalse(app(CookbookSharing::class)->shareWithHousehold($this->cookbook, $foreign, ['can_read' => true], $this->owner));
        $this->assertDatabaseMissing('cookbook_household', ['household_id' => $foreign->id]);
    }

    public function test_the_api_returns_household_recipes_to_members_only(): void
    {
        Passport::actingAs($this->member);
        $this->getJson('/api/recipes/'.$this->recipe->id)->assertNotFound();

        $this->share(['can_read' => true]);

        $this->getJson('/api/recipes/'.$this->recipe->id)->assertOk();
    }

    public function test_the_backend_lists_household_recipes_and_cookbooks(): void
    {
        $this->share(['can_read' => true]);

        $this->actingAs($this->member);

        Livewire::test(ListRecipes::class)->assertCanSeeTableRecords([$this->recipe]);

        $this->get('/admin/cookbooks')->assertOk()->assertSee($this->cookbook->name);
    }

    public function test_the_owner_shares_through_the_cookbook_page(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(HouseholdsRelationManager::class, [
            'ownerRecord' => $this->cookbook,
            'pageClass' => EditCookbook::class,
        ])
            ->callAction(TestAction::make('shareWithHousehold')->table(), [
                'household_id' => $this->household->id,
                'can_read' => true,
                'can_update' => true,
            ])
            ->assertHasNoFormErrors()
            ->assertCanSeeTableRecords([$this->household]);

        $this->assertDatabaseHas('cookbook_household', [
            'cookbook_id' => $this->cookbook->id,
            'household_id' => $this->household->id,
            'can_read' => true,
            'can_update' => true,
            'can_delete' => false,
        ]);

        Livewire::test(HouseholdsRelationManager::class, [
            'ownerRecord' => $this->cookbook,
            'pageClass' => EditCookbook::class,
        ])
            ->callAction(TestAction::make('detach')->table($this->household));

        $this->assertDatabaseMissing('cookbook_household', ['cookbook_id' => $this->cookbook->id]);
    }

    public function test_the_cookbook_page_shows_the_household_shares(): void
    {
        $this->share(['can_read' => true]);

        $this->actingAs($this->owner)
            ->get('/admin/cookbooks/'.$this->cookbook->getRouteKey().'/edit')
            ->assertOk()
            ->assertSee(__('Shared with households'));
    }

    public function test_a_read_only_member_cannot_see_the_household_shares_panel(): void
    {
        $this->share(['can_read' => true]);

        $this->assertFalse(HouseholdsRelationManager::canViewForRecord($this->cookbook, EditCookbook::class));

        $this->actingAs($this->member);

        $this->assertFalse(HouseholdsRelationManager::canViewForRecord($this->cookbook, EditCookbook::class));
    }
}
