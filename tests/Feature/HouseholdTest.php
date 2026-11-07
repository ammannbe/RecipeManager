<?php

namespace Tests\Feature;

use App\Enums\HouseholdRole;
use App\Exceptions\LastHouseholdAdmin;
use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Filament\Resources\Households\Pages\ViewHousehold;
use App\Filament\Resources\Households\RelationManagers\InvitationsRelationManager;
use App\Filament\Resources\Households\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Households\Widgets\PendingHouseholdInvitations;
use App\Mail\HouseholdInvitationMail;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Services\Households;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Local household management (HOUSEHOLDS_DRIVER=local) and its read-only "account-manager" mode.
 */
class HouseholdTest extends TestCase
{
    private function households(): Households
    {
        return app(Households::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: HouseholdInvitation, 1: string}
     */
    private function invitation(array $attributes = []): array
    {
        $token = HouseholdInvitation::newToken();

        $invitation = HouseholdInvitation::factory()->create([
            'token_hash' => HouseholdInvitation::hash($token),
            ...$attributes,
        ]);

        return [$invitation, $token];
    }

    public function test_the_creator_becomes_admin(): void
    {
        $user = User::factory()->create();

        $household = $this->households()->create($user, ' Familie Muster ');

        $this->assertSame('Familie Muster', $household->name);
        $this->assertNotEmpty($household->uuid);
        $this->assertSame($user->id, $household->created_by);
        $this->assertSame(HouseholdRole::Admin, $household->roleOf($user));
    }

    public function test_the_list_shows_only_own_households_and_creates_new_ones(): void
    {
        $user = User::factory()->create();
        $own = Household::factory()->withMember($user)->create();
        $foreign = Household::factory()->create();

        $this->actingAs($user);

        Livewire::test(ListHouseholds::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign])
            ->callAction('create', ['name' => 'WG'])
            ->assertHasNoFormErrors();

        $household = Household::query()->where('name', 'WG')->firstOrFail();
        $this->assertTrue($household->isAdministeredBy($user));
    }

    public function test_global_admins_do_not_see_foreign_households(): void
    {
        $admin = User::factory()->admin()->create();
        $household = Household::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/households/'.$household->getRouteKey())
            ->assertNotFound();
    }

    public function test_a_member_views_but_cannot_manage(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::factory()->administeredBy($admin)->withMember($member)->create();

        $this->actingAs($member)
            ->get('/admin/households/'.$household->getRouteKey())
            ->assertOk()
            ->assertSee($household->name);

        $this->assertFalse($member->can('update', $household));
        $this->assertFalse($member->can('delete', $household));
        $this->assertFalse($member->can('manageMembers', $household));
        $this->assertTrue($member->can('leave', $household));

        Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
            ->assertActionHidden('edit')
            ->assertActionHidden('delete')
            ->assertActionVisible('leave');

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class])
            ->assertCanSeeTableRecords([$admin, $member])
            ->assertActionHidden(TestAction::make('invite')->table())
            ->assertActionHidden(TestAction::make('remove')->table($admin));

        $this->assertFalse(InvitationsRelationManager::canViewForRecord($household, ViewHousehold::class));
    }

    public function test_an_admin_renames_and_deletes(): void
    {
        $admin = User::factory()->create();
        $household = Household::factory()->administeredBy($admin)->create();

        $this->actingAs($admin);

        Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
            ->callAction('edit', ['name' => 'Neuer Name'])
            ->assertHasNoFormErrors();

        $this->assertSame('Neuer Name', $household->refresh()->name);

        Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
            ->callAction('delete');

        $this->assertModelMissing($household);
    }

    public function test_an_admin_invites_by_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $household = Household::factory()->administeredBy($admin)->create();

        $this->actingAs($admin);

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class])
            ->callAction(TestAction::make('invite')->table(), ['email' => 'Friend@Example.com', 'role' => 'member'])
            ->assertHasNoFormErrors();

        $token = null;
        Mail::assertSent(HouseholdInvitationMail::class, function (HouseholdInvitationMail $mail) use (&$token): bool {
            $token = $mail->token;

            return $mail->hasTo('friend@example.com');
        });

        $invitation = HouseholdInvitation::query()->firstOrFail();
        $this->assertSame('friend@example.com', $invitation->email);
        $this->assertSame(HouseholdRole::Member, $invitation->role);
        $this->assertSame(HouseholdInvitation::hash((string) $token), $invitation->token_hash);
    }

    public function test_the_invitation_mail_links_to_the_invitation(): void
    {
        $inviter = User::factory()->create();
        $inviter->author->update(['name' => 'Anna']);
        [$invitation] = $this->invitation(['invited_by' => $inviter->id]);

        $html = (new HouseholdInvitationMail($invitation, 'plain-token'))->render();

        $this->assertStringContainsString(e($invitation->household->name), $html);
        $this->assertStringContainsString('Anna', $html);
        $this->assertStringContainsString(route('household-invitations.show', ['token' => 'plain-token']), $html);
    }

    public function test_the_pages_render_for_an_admin(): void
    {
        $admin = User::factory()->create();
        $household = Household::factory()->administeredBy($admin)->create(['name' => 'Familie Muster']);

        $this->actingAs($admin)
            ->get('/admin/households')
            ->assertOk()
            ->assertSee('Familie Muster');

        $this->actingAs($admin)
            ->get('/admin/households/'.$household->getRouteKey())
            ->assertOk()
            ->assertSee(__('Members'))
            ->assertSee(__('Pending invitations'))
            ->assertSee(__('Shared cookbooks'));
    }

    public function test_existing_users_are_invited_too_and_members_are_skipped(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        User::factory()->create(['email' => 'existing@example.com']);
        $household = Household::factory()->administeredBy($admin)->withMember($member)->create();

        $this->households()->invite($household, 'existing@example.com', HouseholdRole::Member, $admin);
        $this->households()->invite($household, 'MEMBER@example.com', HouseholdRole::Member, $admin);

        Mail::assertSentCount(1);
        $this->assertDatabaseHas('household_invitations', ['email' => 'existing@example.com']);
        $this->assertDatabaseMissing('household_invitations', ['email' => 'member@example.com']);
        $this->assertDatabaseCount('household_user', 2);
    }

    public function test_the_invitation_link_adds_the_invited_user(): void
    {
        [$invitation, $token] = $this->invitation(['email' => 'friend@example.com', 'role' => 'admin']);
        $user = User::factory()->create(['email' => 'Friend@example.com']);

        $this->actingAs($user)
            ->get(route('household-invitations.show', ['token' => $token]))
            ->assertRedirect('/admin/households/'.$invitation->household->getRouteKey());

        $this->assertSame(HouseholdRole::Admin, $invitation->household->roleOf($user));
        $this->assertNotNull($invitation->refresh()->accepted_at);
    }

    public function test_another_address_cannot_accept(): void
    {
        [$invitation, $token] = $this->invitation(['email' => 'friend@example.com']);
        $attacker = User::factory()->create(['email' => 'attacker@example.com']);

        $this->actingAs($attacker)
            ->get(route('household-invitations.show', ['token' => $token]))
            ->assertRedirect(route('recipes.index'));

        $this->assertFalse($invitation->household->hasMember($attacker));
        $this->assertNull($invitation->refresh()->accepted_at);
    }

    public function test_an_unverified_address_cannot_accept(): void
    {
        [$invitation, $token] = $this->invitation(['email' => 'friend@example.com']);
        $user = User::factory()->unverified()->create(['email' => 'friend@example.com']);

        $this->actingAs($user)
            ->get(route('household-invitations.show', ['token' => $token]))
            ->assertRedirect(route('filament.app.auth.email-verification.prompt'))
            ->assertSessionHas('household_invitation_token', $token);

        $this->assertFalse($invitation->household->hasMember($user));
        $this->assertFalse($this->households()->accept($invitation, $user));
    }

    public function test_verifying_the_address_accepts_the_invitation_of_the_session(): void
    {
        [$invitation, $token] = $this->invitation(['email' => 'newcomer@example.com']);
        [$other] = $this->invitation(['email' => 'newcomer@example.com']);
        $user = User::factory()->create(['email' => 'newcomer@example.com']);

        $this->withSession(['household_invitation_token' => $token])->get('/');
        Event::dispatch(new Verified($user));

        $this->assertTrue($invitation->household->hasMember($user));
        // Other invitations stay pending until accepted explicitly.
        $this->assertFalse($other->household->hasMember($user));
    }

    public function test_a_guest_is_sent_to_login_and_returns_to_the_invitation(): void
    {
        [, $token] = $this->invitation(['email' => 'friend@example.com']);
        $url = route('household-invitations.show', ['token' => $token]);

        $this->get($url)
            ->assertRedirect(route('filament.app.auth.login'))
            ->assertSessionHas('household_invitation_token', $token)
            ->assertSessionHas('url.intended', $url);
    }

    public function test_expired_and_used_invitations_are_rejected(): void
    {
        [$expired, $expiredToken] = $this->invitation(['email' => 'friend@example.com', 'expires_at' => now()->subMinute()]);
        [$used, $usedToken] = $this->invitation(['email' => 'friend@example.com', 'accepted_at' => now()]);
        $user = User::factory()->create(['email' => 'friend@example.com']);

        foreach ([$expiredToken, $usedToken] as $token) {
            $this->actingAs($user)
                ->get(route('household-invitations.show', ['token' => $token]))
                ->assertRedirect(route('recipes.index'));
        }

        $this->assertFalse($expired->household->hasMember($user));
        $this->assertFalse($used->household->hasMember($user));
        $this->assertFalse($this->households()->accept($expired, $user));
    }

    public function test_pending_invitations_are_accepted_or_declined_on_the_households_page(): void
    {
        [$accept] = $this->invitation(['email' => 'friend@example.com']);
        [$decline] = $this->invitation(['email' => 'friend@example.com']);
        [$foreign] = $this->invitation(['email' => 'someone@example.com']);
        $user = User::factory()->create(['email' => 'friend@example.com']);

        $this->actingAs($user);

        $this->assertTrue(PendingHouseholdInvitations::canView());

        Livewire::test(PendingHouseholdInvitations::class)
            ->assertCanSeeTableRecords([$accept, $decline])
            ->assertCanNotSeeTableRecords([$foreign])
            ->callTableAction('accept', $accept);

        Livewire::test(PendingHouseholdInvitations::class)
            ->callTableAction('decline', $decline);

        $this->assertTrue($accept->household->hasMember($user));
        $this->assertFalse($decline->household->hasMember($user));
        $this->assertModelMissing($decline);
        $this->assertFalse(PendingHouseholdInvitations::canView());
    }

    public function test_the_last_admin_cannot_be_removed_demoted_or_leave(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::factory()->administeredBy($admin)->withMember($member)->create();

        foreach ([
            fn () => $this->households()->removeMember($household, $admin),
            fn () => $this->households()->leave($household, $admin),
            fn () => $this->households()->changeRole($household, $admin, HouseholdRole::Member),
        ] as $change) {
            try {
                $change();
                $this->fail('The last admin was let go.');
            } catch (LastHouseholdAdmin) {
                // expected
            }
        }

        $this->assertSame(HouseholdRole::Admin, $household->roleOf($admin));

        // With a second admin it works.
        $this->households()->changeRole($household, $member, HouseholdRole::Admin);
        $this->households()->leave($household, $admin);

        $this->assertFalse($household->hasMember($admin));
        $this->assertSame(HouseholdRole::Admin, $household->roleOf($member));
    }

    public function test_the_ui_reports_the_last_admin_protection(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::factory()->administeredBy($admin)->withMember($member)->create();

        $this->actingAs($admin);

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class])
            ->callAction(TestAction::make('role')->table($admin), ['role' => 'member'])
            ->assertNotified(__('A household needs at least one admin. Make another member admin first.'));

        Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
            ->callAction('leave')
            ->assertNotified(__('A household needs at least one admin. Make another member admin first.'));

        $this->assertSame(HouseholdRole::Admin, $household->roleOf($admin));
    }

    public function test_an_admin_removes_a_member_and_a_member_leaves(): void
    {
        $admin = User::factory()->create();
        [$removed, $leaving] = User::factory()->count(2)->create()->all();
        $household = Household::factory()->administeredBy($admin)->withMember($removed)->withMember($leaving)->create();

        $this->actingAs($admin);

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class])
            ->assertActionHidden(TestAction::make('remove')->table($admin))
            ->callAction(TestAction::make('remove')->table($removed));

        $this->actingAs($leaving);

        Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
            ->callAction('leave')
            ->assertRedirect('/admin/households');

        $this->assertFalse($household->hasMember($removed));
        $this->assertFalse($household->hasMember($leaving));
        $this->assertTrue($household->hasMember($admin));
    }

    public function test_deleting_a_user_hands_the_household_on(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $shared = Household::factory()->administeredBy($admin)->withMember($member)->create();
        $alone = Household::factory()->administeredBy($admin)->create();

        $admin->delete();

        $this->assertSame(HouseholdRole::Admin, $shared->roleOf($member));
        $this->assertModelMissing($alone);
    }

    public function test_the_account_manager_driver_makes_local_management_read_only(): void
    {
        config(['households.driver' => 'account-manager']);

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $household = Household::factory()->administeredBy($admin)->create();
        [$invitation, $token] = $this->invitation(['email' => 'admin@example.com']);

        $this->assertFalse($admin->can('create', Household::class));
        $this->assertFalse($admin->can('update', $household));
        $this->assertFalse($admin->can('delete', $household));
        $this->assertFalse($admin->can('manageMembers', $household));
        $this->assertFalse($admin->can('leave', $household));
        $this->assertTrue($admin->can('view', $household));

        $this->actingAs($admin);

        Livewire::test(ListHouseholds::class)
            ->assertActionHidden('create')
            ->assertCanSeeTableRecords([$household]);

        Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
            ->assertActionHidden('edit')
            ->assertActionHidden('delete')
            ->assertActionHidden('leave');

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class])
            ->assertActionHidden(TestAction::make('invite')->table());

        $this->get(route('household-invitations.show', ['token' => $token]))
            ->assertRedirect(route('recipes.index'));

        $this->assertFalse($invitation->household->hasMember($admin));
        $this->assertFalse(PendingHouseholdInvitations::canView());
    }

    public function test_the_prune_command_removes_expired_and_accepted_invitations(): void
    {
        [$pending] = $this->invitation();
        $this->invitation(['expires_at' => now()->subDay()]);
        $this->invitation(['accepted_at' => now()]);

        $command = $this->artisan('household-invitations:prune');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertDatabaseCount('household_invitations', 1);
        $this->assertModelExists($pending);
    }
}
