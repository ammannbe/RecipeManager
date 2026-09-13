<?php

namespace Tests\Feature;

use App\Filament\Resources\Cookbooks\Pages\EditCookbook;
use App\Filament\Resources\Cookbooks\RelationManagers\MembersRelationManager;
use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\User;
use App\Services\CookbookSharing;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class CookbookInvitationTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: CookbookInvitation, 1: string}
     */
    private function invitation(array $attributes = []): array
    {
        $token = CookbookInvitation::newToken();

        $invitation = CookbookInvitation::factory()->create([
            'token_hash' => CookbookInvitation::hash($token),
            ...$attributes,
        ]);

        return [$invitation, $token];
    }

    public function test_an_invited_user_is_added_on_acceptance(): void
    {
        [$invitation, $token] = $this->invitation(['email' => 'friend@example.com']);
        $user = User::factory()->create(['email' => 'friend@example.com']);

        $this->actingAs($user)
            ->get(route('cookbook-invitations.show', ['token' => $token]))
            ->assertRedirect();

        $this->assertTrue($invitation->cookbook->refresh()->grants($user, 'can_read'));
        $this->assertNotNull($invitation->refresh()->accepted_at);
    }

    /**
     * Possession of the token must not be enough.
     */
    public function test_a_user_with_a_different_email_cannot_accept(): void
    {
        [$invitation, $token] = $this->invitation(['email' => 'friend@example.com']);
        $attacker = User::factory()->create(['email' => 'attacker@example.com']);

        $this->actingAs($attacker)
            ->get(route('cookbook-invitations.show', ['token' => $token]))
            ->assertRedirect(route('recipes.index'));

        $this->assertFalse($invitation->cookbook->refresh()->grants($attacker, 'can_read'));
        $this->assertNull($invitation->refresh()->accepted_at);
    }

    public function test_an_expired_invitation_is_rejected(): void
    {
        [$invitation, $token] = $this->invitation([
            'email' => 'friend@example.com',
            'expires_at' => now()->subDay(),
        ]);

        $user = User::factory()->create(['email' => 'friend@example.com']);

        $this->actingAs($user)
            ->get(route('cookbook-invitations.show', ['token' => $token]))
            ->assertRedirect(route('recipes.index'));

        $this->assertFalse($invitation->cookbook->refresh()->grants($user, 'can_read'));
    }

    public function test_an_accepted_invitation_cannot_be_reused(): void
    {
        [$invitation, $token] = $this->invitation([
            'email' => 'friend@example.com',
            'accepted_at' => now(),
        ]);

        $user = User::factory()->create(['email' => 'friend@example.com']);

        $this->actingAs($user)
            ->get(route('cookbook-invitations.show', ['token' => $token]))
            ->assertRedirect(route('recipes.index'));

        $this->assertFalse($invitation->cookbook->refresh()->grants($user, 'can_read'));
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('cookbook-invitations.show', ['token' => str_repeat('a', 64)]))
            ->assertRedirect(route('recipes.index'));
    }

    public function test_a_guest_is_sent_to_login_and_the_token_is_remembered(): void
    {
        [, $token] = $this->invitation(['email' => 'friend@example.com']);

        $this->get(route('cookbook-invitations.show', ['token' => $token]))
            ->assertRedirect()
            ->assertSessionHas('cookbook_invitation_token', $token);
    }

    public function test_registering_with_the_invited_address_binds_the_invitation(): void
    {
        [$invitation] = $this->invitation(['email' => 'newcomer@example.com']);

        $user = User::factory()->create(['email' => 'newcomer@example.com']);

        Event::dispatch(new Registered($user));

        $this->assertTrue($invitation->cookbook->refresh()->grants($user, 'can_read'));
        $this->assertNotNull($invitation->refresh()->accepted_at);
    }

    public function test_registering_with_another_address_binds_nothing(): void
    {
        [$invitation] = $this->invitation(['email' => 'newcomer@example.com']);

        $user = User::factory()->create(['email' => 'somebody@example.com']);

        Event::dispatch(new Registered($user));

        $this->assertFalse($invitation->cookbook->refresh()->grants($user, 'can_read'));
        $this->assertNull($invitation->refresh()->accepted_at);
    }

    public function test_the_granted_permissions_carry_over(): void
    {
        [$invitation, $token] = $this->invitation([
            'email' => 'friend@example.com',
            'can_update' => true,
        ]);

        $user = User::factory()->create(['email' => 'friend@example.com']);

        $this->actingAs($user)->get(route('cookbook-invitations.show', ['token' => $token]));

        $cookbook = $invitation->cookbook->refresh();

        $this->assertTrue($cookbook->grants($user, 'can_update'));
        $this->assertFalse($cookbook->grants($user, 'can_delete'));
    }

    /**
     * The confirmation must not reveal whether an address belongs to an account.
     */
    public function test_sharing_looks_the_same_for_known_and_unknown_addresses(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $owner->author_id]);
        User::factory()->create(['email' => 'known@example.com']);

        $this->actingAs($owner);

        $share = function (string $email) use ($cookbook): array {
            $component = Livewire::test(MembersRelationManager::class, [
                'ownerRecord' => $cookbook,
                'pageClass' => EditCookbook::class,
            ])->callAction(
                TestAction::make('share')->table(),
                data: ['email' => $email, 'can_read' => true],
            );

            $component->assertHasNoActionErrors();

            // The dispatched effects are what the browser observes.
            /** @var array<int, array{name: string}> $dispatches */
            $dispatches = $component->effects['dispatches'] ?? [];

            return array_column($dispatches, 'name');
        };

        $this->assertSame($share('known@example.com'), $share('unknown@example.com'));
    }

    public function test_the_accept_route_is_rate_limited(): void
    {
        [, $token] = $this->invitation();

        foreach (range(1, 10) as $ignored) {
            $this->get(route('cookbook-invitations.show', ['token' => $token]));
        }

        $this->get(route('cookbook-invitations.show', ['token' => $token]))
            ->assertStatus(429);
    }

    public function test_sharing_with_the_owner_creates_no_membership(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['admin' => false]);
        $cookbook = Cookbook::factory()->create(['author_id' => $owner->author_id]);

        app(CookbookSharing::class)->share($cookbook, $owner->email, ['can_read' => true], $owner);

        $this->assertSame(0, $cookbook->members()->count());
    }
}
