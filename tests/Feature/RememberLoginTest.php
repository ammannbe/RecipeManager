<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The remember-me cookie signs users in again after the session expired, also accounts without password
 * (linked to the OIDC login, User::getAuthPassword()).
 */
class RememberLoginTest extends TestCase
{
    use RefreshDatabase;

    private function guard(): SessionGuard
    {
        $guard = Auth::guard('web');
        $this->assertInstanceOf(SessionGuard::class, $guard);

        return $guard;
    }

    private function recaller(User $user, string $token): string
    {
        return $user->id.'|'.$token.'|'.$this->guard()->hashPasswordForCookie($user->getAuthPassword());
    }

    private function panelUrl(): string
    {
        return Filament::getPanel('app')->getUrl();
    }

    public function test_an_account_without_password_is_signed_in_by_the_remember_cookie(): void
    {
        $user = User::factory()->linked()->create(['remember_token' => 'remember-token']);

        $this->assertNull($user->password);

        $this->withCookie($this->guard()->getRecallerName(), $this->recaller($user, 'remember-token'))
            ->get($this->panelUrl())
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_with_password_is_signed_in_by_the_remember_cookie(): void
    {
        $user = User::factory()->create(['remember_token' => 'remember-token']);

        $this->withCookie($this->guard()->getRecallerName(), $this->recaller($user, 'remember-token'))
            ->get($this->panelUrl())
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_remember_token_is_refused(): void
    {
        $user = User::factory()->linked()->create(['remember_token' => 'remember-token']);

        $this->withCookie($this->guard()->getRecallerName(), $this->recaller($user, 'other-token'))
            ->get($this->panelUrl())
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_an_account_without_password_cannot_sign_in_with_an_empty_password(): void
    {
        $user = User::factory()->linked()->create();

        $this->assertFalse(Auth::guard('web')->validate(['email' => $user->email, 'password' => '']));
    }
}
