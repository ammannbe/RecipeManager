<?php

namespace Tests\Feature;

use App\Filament\Resources\Cookbooks\Pages\EditCookbook;
use App\Filament\Resources\Cookbooks\RelationManagers\InvitationsRelationManager;
use App\Filament\Resources\Cookbooks\RelationManagers\MembersRelationManager;
use App\Mail\CookbookInvitationMail;
use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\Recipe;
use App\Models\User;
use App\Services\CookbookSharing;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class CookbookSharingTest extends TestCase
{
    private function sharing(): CookbookSharing
    {
        return app(CookbookSharing::class);
    }

    /**
     * @param  array<string, bool>  $grants
     */
    private function sharedWith(User $member, array $grants, ?Cookbook $cookbook = null): Cookbook
    {
        $cookbook ??= Cookbook::factory()->create();

        $this->sharing()->addMember($cookbook, $member, $grants);

        return $cookbook->refresh();
    }

    public function test_the_owner_holds_every_grant(): void
    {
        $owner = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $owner->author_id]);

        foreach (['can_read', 'can_create', 'can_update', 'can_delete', 'can_admin'] as $grant) {
            $this->assertTrue($cookbook->grants($owner, $grant), $grant);
        }
    }

    public function test_a_stranger_holds_no_grants(): void
    {
        $cookbook = Cookbook::factory()->create();
        $stranger = User::factory()->create(['admin' => false]);

        foreach (['can_read', 'can_create', 'can_update', 'can_delete', 'can_admin'] as $grant) {
            $this->assertFalse($cookbook->grants($stranger, $grant), $grant);
        }
    }

    public function test_a_read_only_member_cannot_edit_or_delete(): void
    {
        $member = User::factory()->create(['admin' => false]);
        $cookbook = $this->sharedWith($member, ['can_read' => true]);

        $this->assertTrue($cookbook->grants($member, 'can_read'));
        $this->assertFalse($cookbook->grants($member, 'can_update'));
        $this->assertFalse($cookbook->grants($member, 'can_delete'));
        $this->assertFalse($cookbook->grants($member, 'can_admin'));
    }

    public function test_a_cookbook_admin_holds_the_other_grants_too(): void
    {
        $member = User::factory()->create(['admin' => false]);
        $cookbook = $this->sharedWith($member, ['can_admin' => true]);

        foreach (['can_read', 'can_create', 'can_update', 'can_delete'] as $grant) {
            $this->assertTrue($cookbook->grants($member, $grant), $grant);
        }
    }

    public function test_a_read_only_member_may_view_but_not_manage_the_cookbook(): void
    {
        $member = User::factory()->create(['admin' => false]);
        $cookbook = $this->sharedWith($member, ['can_read' => true]);

        $this->assertTrue($member->can('view', $cookbook));
        $this->assertFalse($member->can('update', $cookbook));
        $this->assertFalse($member->can('delete', $cookbook));
        $this->assertFalse($member->can('share', $cookbook));
    }

    public function test_a_cookbook_admin_may_manage_the_cookbook(): void
    {
        $member = User::factory()->create(['admin' => false]);
        $cookbook = $this->sharedWith($member, ['can_admin' => true]);

        $this->assertTrue($member->can('update', $cookbook));
        $this->assertTrue($member->can('share', $cookbook));
        $this->assertFalse($member->can('forceDelete', $cookbook));
    }

    public function test_update_grant_covers_recipes_written_by_someone_else(): void
    {
        $member = User::factory()->create(['admin' => false]);
        $cookbook = $this->sharedWith($member, ['can_update' => true]);

        $recipe = Recipe::factory()->create([
            'cookbook_id' => $cookbook->id,
            'author_id' => $cookbook->author_id,
        ]);

        $this->assertTrue($member->can('view', $recipe));
        $this->assertTrue($member->can('update', $recipe));
        $this->assertFalse($member->can('delete', $recipe));
    }

    public function test_delete_grant_covers_recipes_in_the_cookbook(): void
    {
        $member = User::factory()->create(['admin' => false]);
        $cookbook = $this->sharedWith($member, ['can_delete' => true]);

        $recipe = Recipe::factory()->create([
            'cookbook_id' => $cookbook->id,
            'author_id' => $cookbook->author_id,
        ]);

        $this->assertTrue($member->can('delete', $recipe));
    }

    public function test_a_read_only_member_cannot_edit_recipes(): void
    {
        $member = User::factory()->create(['admin' => false]);
        $cookbook = $this->sharedWith($member, ['can_read' => true]);

        $recipe = Recipe::factory()->create([
            'cookbook_id' => $cookbook->id,
            'author_id' => $cookbook->author_id,
        ]);

        $this->assertTrue($member->can('view', $recipe));
        $this->assertFalse($member->can('update', $recipe));
        $this->assertFalse($member->can('delete', $recipe));
    }

    public function test_a_member_sees_the_shared_cookbook_and_its_recipes(): void
    {
        $member = User::factory()->create(['admin' => false]);
        $cookbook = $this->sharedWith($member, ['can_read' => true]);
        $cookbook->update(['name' => 'Shared book']);

        Recipe::factory()->create([
            'cookbook_id' => $cookbook->id,
            'author_id' => $cookbook->author_id,
            'is_public' => false,
            'name' => 'Shared recipe',
        ]);

        $this->actingAs($member)
            ->get('/admin/cookbooks')
            ->assertOk()
            ->assertSee('Shared book');

        $this->actingAs($member)
            ->get('/admin/recipes')
            ->assertOk()
            ->assertSee('Shared recipe');
    }

    public function test_a_non_member_cannot_reach_a_private_recipe_by_guessing(): void
    {
        $cookbook = Cookbook::factory()->create(['is_public' => false]);
        $recipe = Recipe::factory()->create([
            'cookbook_id' => $cookbook->id,
            'author_id' => $cookbook->author_id,
            'is_public' => false,
        ]);

        $this->actingAs(User::factory()->create(['admin' => false]))
            ->get(route('recipes.show', $recipe))
            ->assertNotFound();
    }

    public function test_revoking_a_membership_removes_access_immediately(): void
    {
        $member = User::factory()->create(['admin' => false]);
        $cookbook = $this->sharedWith($member, ['can_read' => true]);

        $this->assertTrue($member->can('view', $cookbook));

        $cookbook->members()->detach($member->getKey());

        $this->assertFalse($member->fresh()->can('view', $cookbook->fresh()));
    }

    public function test_sharing_with_an_existing_user_adds_them_directly(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $owner->author_id]);
        $invitee = User::factory()->create(['email' => 'friend@example.com']);

        $this->sharing()->share($cookbook, 'friend@example.com', ['can_read' => true], $owner);

        $this->assertTrue($cookbook->refresh()->grants($invitee, 'can_read'));
        Mail::assertNothingSent();
    }

    public function test_sharing_is_case_insensitive_for_existing_users(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $owner->author_id]);
        $invitee = User::factory()->create(['email' => 'friend@example.com']);

        $this->sharing()->share($cookbook, 'FRIEND@Example.com', ['can_read' => true], $owner);

        $this->assertTrue($cookbook->refresh()->grants($invitee, 'can_read'));
    }

    public function test_sharing_with_an_unknown_email_sends_an_invitation(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $owner->author_id]);

        $this->sharing()->share($cookbook, 'stranger@example.com', ['can_read' => true], $owner);

        Mail::assertSent(CookbookInvitationMail::class);

        $this->assertDatabaseHas('cookbook_invitations', [
            'cookbook_id' => $cookbook->id,
            'email' => 'stranger@example.com',
            'accepted_at' => null,
        ]);
    }

    public function test_the_owner_sees_the_sharing_panels(): void
    {
        $owner = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $owner->author_id]);

        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->sharing()->addMember($cookbook, $member, ['can_update' => true]);

        CookbookInvitation::factory()->create([
            'cookbook_id' => $cookbook->id,
            'email' => 'pending@example.com',
        ]);

        $this->actingAs($owner)
            ->get('/admin/cookbooks/'.$cookbook->getRouteKey().'/edit')
            ->assertOk()
            ->assertSee(__('Shared with'))
            ->assertSee(__('Pending invitations'));

        // Relation manager rows load lazily, so assert on the components themselves.
        Livewire::test(MembersRelationManager::class, [
            'ownerRecord' => $cookbook,
            'pageClass' => EditCookbook::class,
        ])->assertCanSeeTableRecords([$member]);

        Livewire::test(InvitationsRelationManager::class, [
            'ownerRecord' => $cookbook,
            'pageClass' => EditCookbook::class,
        ])->assertSee('pending@example.com');
    }

    public function test_a_read_only_member_cannot_open_the_cookbook_edit_page(): void
    {
        $member = User::factory()->create(['admin' => false]);
        $cookbook = $this->sharedWith($member, ['can_read' => true]);

        $this->actingAs($member)
            ->get('/admin/cookbooks/'.$cookbook->getRouteKey().'/edit')
            ->assertForbidden();
    }

    public function test_the_invitation_token_is_never_stored_in_plaintext(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $owner->author_id]);

        $this->sharing()->share($cookbook, 'stranger@example.com', ['can_read' => true], $owner);

        $token = null;

        Mail::assertSent(CookbookInvitationMail::class, function (CookbookInvitationMail $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        $invitation = CookbookInvitation::query()->firstOrFail();

        $this->assertNotNull($token);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(CookbookInvitation::hash((string) $token), $invitation->token_hash);
    }
}
