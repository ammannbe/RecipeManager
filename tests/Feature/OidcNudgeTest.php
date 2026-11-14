<?php

namespace Tests\Feature;

use App\Auth\OidcNudge;
use App\Filament\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AUTH_OIDC_NUDGE: banner in the panel offering unlinked users to link the OIDC sign-in.
 */
class OidcNudgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-14 12:00');
        Filament::setCurrentPanel('app');

        config([
            'login.oidc.enabled' => true,
            'login.oidc.label' => 'NarrenKonto',
            'login.oidc.nudge' => true,
            'login.oidc.nudge_days' => 0,
        ]);
    }

    private function dashboard(User $user): string
    {
        return (string) $this->actingAs($user)->get(Filament::getPanel('app')->getUrl())->assertOk()->getContent();
    }

    public function test_an_unlinked_user_sees_the_banner_with_the_link_flow(): void
    {
        $user = User::factory()->create();

        $html = $this->dashboard($user);

        $this->assertStringContainsString('data-oidc-nudge', $html);
        $this->assertStringContainsString('Link with NarrenKonto', $html);
        $this->assertStringContainsString(e(EditProfile::getUrl(['action' => 'linkOidc'], panel: 'app')), $html);
        $this->assertStringContainsString(route('oidc.nudge.dismiss'), $html);
    }

    public function test_the_banner_is_translated(): void
    {
        $user = User::factory()->create(['locale' => 'de']);

        $this->assertStringContainsString('Mit NarrenKonto verknüpfen', $this->dashboard($user));
        $this->assertStringContainsString('Jetzt verknüpfen', $this->dashboard($user));
    }

    public function test_linked_users_do_not_see_it(): void
    {
        $user = User::factory()->linked()->create();

        $this->assertStringNotContainsString('data-oidc-nudge', $this->dashboard($user));
    }

    public function test_it_is_off_without_the_switch_or_the_oidc_login(): void
    {
        $user = User::factory()->create();

        config(['login.oidc.nudge' => false]);
        $this->assertStringNotContainsString('data-oidc-nudge', $this->dashboard($user));

        config(['login.oidc.nudge' => true, 'login.oidc.enabled' => false]);
        $this->assertStringNotContainsString('data-oidc-nudge', $this->dashboard($user));
    }

    public function test_later_hides_it_for_good_without_nudge_days(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(Filament::getPanel('app')->getUrl())
            ->post(route('oidc.nudge.dismiss'))
            ->assertRedirect(Filament::getPanel('app')->getUrl());

        $this->assertNotNull($user->fresh()?->oidc_nudge_dismissed_at);

        $this->travel(5)->years();
        $this->assertFalse(OidcNudge::shows($user->fresh()));
        $this->assertStringNotContainsString('data-oidc-nudge', $this->dashboard($user->refresh()));
    }

    public function test_it_comes_back_after_the_nudge_days(): void
    {
        config(['login.oidc.nudge_days' => 7]);
        $user = User::factory()->create(['oidc_nudge_dismissed_at' => null]);

        OidcNudge::dismiss($user);

        $this->travel(6)->days();
        $this->assertFalse(OidcNudge::shows($user->fresh()));

        $this->travel(1)->days();
        $this->assertTrue(OidcNudge::shows($user->fresh()));
    }

    public function test_dismissing_needs_a_signed_in_user(): void
    {
        $this->post(route('oidc.nudge.dismiss'))->assertRedirect(Filament::getPanel('app')->getLoginUrl());
        $this->assertGuest();
    }
}
