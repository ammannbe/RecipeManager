<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Filament\Auth\Register;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * AUTH_LOCAL_REGISTRATION=false: new accounts only through the OIDC provider (e.g. the AccountManager's sign-up).
 */
class LocalRegistrationSwitchTest extends TestCase
{
    private const SIGNUP = 'https://konto.example.com/register';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
    }

    private function loginPage(): Login
    {
        $page = Livewire::test(Login::class)->instance();
        $this->assertInstanceOf(Login::class, $page);

        return $page;
    }

    public function test_the_login_page_links_to_the_local_registration_by_default(): void
    {
        $this->get(Filament::getPanel('app')->getLoginUrl())
            ->assertOk()
            ->assertSee(Filament::getPanel('app')->getRegistrationUrl(), false);

        $this->get(Filament::getPanel('app')->getRegistrationUrl())->assertOk();
    }

    public function test_without_local_registration_the_register_page_redirects_to_the_signup(): void
    {
        config(['login.local.registration' => false, 'login.oidc.signup_url' => self::SIGNUP]);

        $this->get(Filament::getPanel('app')->getRegistrationUrl())->assertRedirect(self::SIGNUP);
    }

    public function test_without_local_registration_and_signup_url_the_register_page_redirects_to_the_login(): void
    {
        config(['login.local.registration' => false, 'login.oidc.signup_url' => null]);

        $this->get(Filament::getPanel('app')->getRegistrationUrl())
            ->assertRedirect(Filament::getPanel('app')->getLoginUrl());
    }

    public function test_without_the_local_login_registration_is_off_too(): void
    {
        config(['login.local.enabled' => false, 'login.oidc.signup_url' => self::SIGNUP]);

        $this->assertFalse(Register::enabled());
        $this->get(Filament::getPanel('app')->getRegistrationUrl())->assertRedirect(self::SIGNUP);
    }

    public function test_without_local_registration_submitting_the_form_is_refused(): void
    {
        $component = Livewire::test(Register::class)->fillForm([
            'name' => 'New Cook',
            'email' => 'new.cook@example.com',
            'password' => 'secret-password-123',
            'passwordConfirmation' => 'secret-password-123',
        ]);

        config(['login.local.registration' => false]);
        $this->travel(Register::MIN_SECONDS_TO_SUBMIT)->seconds();

        $component->call('register')->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new.cook@example.com']);
        $this->assertSame(0, User::query()->count());
    }

    public function test_without_local_registration_the_login_links_to_the_signup(): void
    {
        config(['login.local.registration' => false, 'login.oidc.signup_url' => self::SIGNUP]);

        $this->get(Filament::getPanel('app')->getLoginUrl())
            ->assertOk()
            ->assertSee(self::SIGNUP, false)
            ->assertDontSee(Filament::getPanel('app')->getRegistrationUrl(), false);
    }

    public function test_without_local_registration_and_signup_url_the_login_has_no_register_link(): void
    {
        config(['login.local.registration' => false, 'login.oidc.signup_url' => null]);

        $this->get(Filament::getPanel('app')->getLoginUrl())
            ->assertOk()
            ->assertDontSee(Filament::getPanel('app')->getRegistrationUrl(), false);

        $this->assertNull($this->loginPage()->getSubheading());
    }

    public function test_with_the_oidc_login_the_signup_is_offered_once_below_the_button(): void
    {
        config([
            'login.local.registration' => false,
            'login.oidc.enabled' => true,
            'login.oidc.label' => 'NarrenKonto',
            'login.oidc.signup_url' => self::SIGNUP,
        ]);

        $html = $this->get(Filament::getPanel('app')->getLoginUrl())->assertOk()->getContent();

        $this->assertSame(1, substr_count((string) $html, self::SIGNUP));
        $this->assertNull($this->loginPage()->getSubheading());
    }
}
