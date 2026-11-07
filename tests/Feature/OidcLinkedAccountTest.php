<?php

namespace Tests\Feature;

use App\Auth\OidcAccount;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\User;
use App\Notifications\SignInWithOidcNotice;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\Feature\Concerns\MocksOidcProvider;
use Tests\TestCase;

/**
 * A linked account (users.oidc_sub) signs in through the OIDC provider only (App\Auth\OidcAccount).
 */
class OidcLinkedAccountTest extends TestCase
{
    use MocksOidcProvider;

    private const PASSWORD = 'secret-password';

    private const NEW_PASSWORD = 'new-secret-password';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'login.oidc.enabled' => true,
            'login.oidc.label' => 'Pocket ID',
            'login.oidc.registration' => true,
            'login.oidc.required_group' => null,
            'session.driver' => 'database',
        ]);

        Filament::setCurrentPanel('app');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function localUser(array $attributes = []): User
    {
        return User::factory()->create(['email' => 'cook@example.com', 'password' => Hash::make(self::PASSWORD), ...$attributes]);
    }

    private function foreignSession(User $user, string $id = 'other-device'): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function command(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    private function loginMessage(): string
    {
        return __('This account is connected to :provider. Sign in with :provider.', ['provider' => 'Pocket ID']);
    }

    public function test_the_first_oidc_sign_in_links_the_account_and_removes_the_local_login(): void
    {
        $user = $this->localUser(['remember_token' => 'old-remember-token']);
        $this->foreignSession($user);
        Password::broker()->createToken($user);

        $this->signIn()->assertCookie(Auth::guard()->getRecallerName());

        $user->refresh();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('sub-123', $user->oidc_sub);
        $this->assertNull($user->password);
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'cook@example.com']);
    }

    public function test_linking_from_the_profile_needs_the_password(): void
    {
        $user = $this->localUser(['email' => 'different@example.com']);
        $provider = $this->provider();
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://login.example.com/authorize'));
        $provider->shouldReceive('user')->andReturn($this->identity());
        $this->actingAs($user);

        // A session alone does not link (and does not go to the provider).
        $this->get(route('oidc.redirect', ['link' => 1]))->assertRedirect(EditProfile::getUrl(panel: 'app'));
        $this->assertFalse(session()->has('oidc.intent'));

        Livewire::test(EditProfile::class)
            ->callAction('linkOidc', ['currentPassword' => 'wrong-password'])
            ->assertHasActionErrors(['currentPassword']);

        Livewire::test(EditProfile::class)
            ->callAction('linkOidc', ['currentPassword' => self::PASSWORD])
            ->assertHasNoActionErrors()
            ->assertRedirect(route('oidc.redirect', ['link' => 1]));

        $this->get(route('oidc.redirect', ['link' => 1]))->assertRedirect('https://login.example.com/authorize');
        $this->foreignSession($user);
        $this->get(route('oidc.callback'))->assertRedirect(EditProfile::getUrl(panel: 'app'));

        $user->refresh();
        $this->assertSame('sub-123', $user->oidc_sub);
        $this->assertNull($user->password);
        // Other devices are signed out, this one stays signed in.
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_linked_account_cannot_sign_in_with_a_password(): void
    {
        $user = User::factory()->linked()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'any-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => $this->loginMessage()]);

        $this->assertGuest();
    }

    public function test_a_password_left_on_a_linked_account_does_not_sign_in(): void
    {
        $user = User::factory()->linked()->create();
        DB::table('users')->where('id', $user->id)->update(['password' => Hash::make(self::PASSWORD)]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => self::PASSWORD])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => $this->loginMessage()]);

        $this->assertGuest();
    }

    public function test_other_failed_logins_keep_the_usual_message(): void
    {
        $this->localUser();

        foreach (['cook@example.com', 'nobody@example.com'] as $email) {
            Livewire::test(Login::class)
                ->fillForm(['email' => $email, 'password' => 'wrong-password'])
                ->call('authenticate')
                ->assertHasFormErrors(['email' => __('filament-panels::auth/pages/login.messages.failed')]);
        }

        $this->assertGuest();
    }

    public function test_a_password_reset_for_a_linked_account_only_points_to_the_provider(): void
    {
        Notification::fake();
        $linked = User::factory()->linked()->create();
        $local = $this->localUser();
        $sent = __(Password::RESET_LINK_SENT);

        // The page answers the same for both, the mail tells the owner of the linked account to use the provider.
        foreach ([$linked, $local] as $user) {
            Livewire::test(RequestPasswordReset::class)
                ->fillForm(['email' => $user->email])
                ->call('request')
                ->assertHasNoFormErrors()
                ->assertNotified($sent);
        }

        Notification::assertSentTo($linked, SignInWithOidcNotice::class);
        Notification::assertNotSentTo($linked, ResetPasswordNotification::class);
        Notification::assertSentTo($local, ResetPasswordNotification::class);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $linked->email]);

        // Once per NOTICE_DECAY_SECONDS (after the page's own limit of two requests a minute).
        $this->travel(61)->seconds();
        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => $linked->email])->call('request')->assertNotified($sent);
        Notification::assertSentToTimes($linked, SignInWithOidcNotice::class, 1);
    }

    public function test_a_reset_link_cannot_give_a_linked_account_a_password(): void
    {
        $user = User::factory()->linked()->create();
        $token = Password::broker()->createToken($user);

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
            ->fillForm(['password' => self::NEW_PASSWORD, 'passwordConfirmation' => self::NEW_PASSWORD])
            ->call('resetPassword')
            ->assertNotified($this->loginMessage());

        $this->assertNull($user->fresh()->password);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_an_unlinked_account_without_password_can_still_reset_it(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => null]);

        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => $user->email])->call('request');
        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $token = Password::broker()->createToken($user);

        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
            ->fillForm(['password' => self::NEW_PASSWORD, 'passwordConfirmation' => self::NEW_PASSWORD])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, (string) $user->fresh()->password));
    }

    public function test_unlinking_needs_a_recent_oidc_sign_in(): void
    {
        $user = User::factory()->linked()->create();
        $provider = $this->provider();
        $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://login.example.com/authorize'));
        $provider->shouldReceive('user')->andReturn($this->identity(['sub' => 'someone-else']), $this->identity());
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->mountAction('unlinkOidc')
            ->assertMountedActionModalSee('For your security, confirm with Pocket ID first.')
            ->callMountedAction()
            ->assertRedirect(route('oidc.redirect', ['confirm' => 1]));
        $this->assertNotNull($user->fresh()->oidc_sub);

        $this->get(route('oidc.redirect', ['confirm' => 1]))->assertRedirect('https://login.example.com/authorize');

        // Another provider account does not confirm.
        $this->get(route('oidc.callback'))->assertRedirect(EditProfile::getUrl(panel: 'app'));
        $this->assertFalse(app(OidcAccount::class)->hasRecentSignIn());
        $this->assertAuthenticatedAs($user);

        $this->get(route('oidc.redirect', ['confirm' => 1]));
        $this->get(route('oidc.callback'))->assertRedirect(EditProfile::getUrl(['action' => 'unlinkOidc'], panel: 'app'));
        $this->assertTrue(app(OidcAccount::class)->hasRecentSignIn());

        $this->travel(OidcAccount::SIGN_IN_TIMEOUT + 1)->seconds();
        $this->assertFalse(app(OidcAccount::class)->hasRecentSignIn());
    }

    public function test_unlinking_needs_a_valid_new_password_and_then_allows_the_local_login(): void
    {
        $user = User::factory()->linked()->create();
        $this->actingAs($user);
        app(OidcAccount::class)->rememberSignIn();
        $this->foreignSession($user);

        Livewire::test(EditProfile::class)
            ->callAction('unlinkOidc', ['password' => 'short', 'passwordConfirmation' => 'short'])
            ->assertHasActionErrors(['password']);
        Livewire::test(EditProfile::class)
            ->callAction('unlinkOidc', ['password' => self::NEW_PASSWORD, 'passwordConfirmation' => 'something-else'])
            ->assertHasActionErrors(['password']);
        Livewire::test(EditProfile::class)
            ->callAction('unlinkOidc', ['password' => '', 'passwordConfirmation' => ''])
            ->assertHasActionErrors(['password']);
        $this->assertSame('sub-123', $user->fresh()->oidc_sub);

        Livewire::test(EditProfile::class)
            ->callAction('unlinkOidc', ['password' => self::NEW_PASSWORD, 'passwordConfirmation' => self::NEW_PASSWORD])
            ->assertHasNoActionErrors();

        $user->refresh();
        $this->assertNull($user->oidc_sub);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, (string) $user->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);

        Auth::logout();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => self::NEW_PASSWORD])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_profile_of_a_linked_account_has_no_password_fields(): void
    {
        $this->actingAs(User::factory()->linked()->create());

        Livewire::test(EditProfile::class)
            ->assertSee('Connected with Pocket ID')
            ->assertFormFieldDoesNotExist('password')
            ->assertFormFieldDoesNotExist('currentPassword')
            ->assertFormFieldDisabled('email')
            ->assertActionVisible('unlinkOidc')
            ->assertActionHidden('linkOidc');

        $this->get(EditProfile::getUrl(panel: 'app'))->assertOk()->assertSee('Connected with Pocket ID');
    }

    public function test_the_profile_of_an_unlinked_account_keeps_the_password_fields(): void
    {
        $this->actingAs($this->localUser());

        Livewire::test(EditProfile::class)
            ->assertDontSee('Connected with Pocket ID')
            ->assertFormFieldExists('password')
            ->assertFormFieldEnabled('email')
            ->assertActionVisible('linkOidc')
            ->assertActionHidden('unlinkOidc');
    }

    public function test_admins_see_the_sign_in_and_cannot_set_a_password_on_a_linked_account(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $linked = User::factory()->linked()->create();

        Livewire::test(ListAuthors::class)->assertSee('Pocket ID');

        Livewire::test(EditAuthor::class, ['record' => $linked->author_id])
            ->assertSee('Connected with Pocket ID')
            ->assertFormFieldHidden('user.password');
    }

    public function test_a_linked_account_cannot_get_a_password_on_the_model(): void
    {
        $user = User::factory()->linked()->create();

        $this->expectException(LogicException::class);

        $user->forceFill(['password' => self::NEW_PASSWORD])->save();
    }

    public function test_the_migration_removes_the_local_login_of_linked_accounts_only(): void
    {
        $linked = $this->localUser();
        DB::table('users')->where('id', $linked->id)->update(['oidc_sub' => 'sub-123']);
        $admin = User::factory()->admin()->create(['password' => Hash::make(self::PASSWORD)]);
        $withoutPassword = User::factory()->create(['password' => null]);
        $this->foreignSession($linked, 'linked-session');
        $this->foreignSession($admin, 'admin-session');
        Password::broker()->createToken($linked);
        Password::broker()->createToken($withoutPassword);

        $migration = require database_path('migrations/2026_11_13_090000_remove_local_login_from_linked_accounts.php');
        $migration->up();

        $this->assertNull($linked->fresh()->password);
        $this->assertNull(DB::table('users')->where('id', $linked->id)->value('remember_token'));
        $this->assertDatabaseMissing('sessions', ['id' => 'linked-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $linked->email]);

        // Unlinked accounts (emergency admins, accounts without password) are untouched.
        $this->assertTrue(Hash::check(self::PASSWORD, (string) $admin->fresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $withoutPassword->email]);
    }

    public function test_the_command_unlinks_accounts_for_a_new_password(): void
    {
        $linked = User::factory()->linked()->create();
        $other = User::factory()->linked('sub-other')->create();

        $this->command('users:unlink-oidc')->assertFailed();
        $this->command('users:unlink-oidc', ['emails' => [$linked->email]])->assertSuccessful();

        $this->assertNull($linked->fresh()->oidc_sub);
        $this->assertNull($linked->fresh()->password);
        $this->assertSame('sub-other', $other->fresh()->oidc_sub);

        $this->command('users:unlink-oidc', ['--all' => true])->expectsConfirmation('Unlink 1 account(s)?', 'yes')->assertSuccessful();
        $this->assertNull($other->fresh()->oidc_sub);
    }
}
