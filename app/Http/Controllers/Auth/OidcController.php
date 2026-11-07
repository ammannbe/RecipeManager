<?php

namespace App\Http\Controllers\Auth;

use App\Auth\OidcAccount;
use App\Auth\OidcLogin;
use App\Auth\OidcLoginException;
use App\Entitlements\AccountManagerEntitlements;
use App\Entitlements\Entitlements;
use App\Filament\Auth\EditProfile;
use App\Households\AccountManagerHouseholds;
use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;
use UnexpectedValueException;

class OidcController extends Controller
{
    private const INTENT = 'oidc.intent';

    /**
     * "?link=1" links the signed-in user (profile, after confirming the password), "?confirm=1"
     * confirms a linked user's sign-in before unlinking; otherwise a normal sign-in.
     */
    public function redirect(Request $request, OidcAccount $accounts): SymfonyRedirectResponse
    {
        abort_unless(config('login.oidc.enabled'), 404);

        $request->session()->forget(self::INTENT);

        /** @var User|null $user */
        $user = Auth::user();
        $intent = 'login';

        if ($user && $request->boolean('link')) {
            // Linking removes the password, so a session alone (e.g. an unlocked device) is not enough.
            if (! $accounts->pullPasswordConfirmation($user)) {
                return $this->failed(__('Please confirm your password first.'), 'link');
            }

            $intent = 'link';
        } elseif ($user && $request->boolean('confirm')) {
            $intent = 'confirm';
        }

        $request->session()->put(self::INTENT, $intent);

        return $this->provider()->redirect();
    }

    public function callback(Request $request, OidcLogin $login, OidcAccount $accounts, AccountManagerHouseholds $households, AccountManagerEntitlements $entitlements): RedirectResponse
    {
        abort_unless(config('login.oidc.enabled'), 404);

        /** @var User|null $current */
        $current = Auth::user();
        $intent = (string) $request->session()->pull(self::INTENT, 'login');
        $intent = $current ? $intent : 'login';

        try {
            $identity = $this->provider()->user();

            if (! $identity instanceof AbstractUser) {
                throw new UnexpectedValueException('Unexpected OIDC user.');
            }

            if ($intent === 'confirm' && $current) {
                $login->confirm($identity, $current);
                $accounts->rememberSignIn();

                // Opens the unlink form right away.
                return redirect()->to(EditProfile::getUrl(['action' => 'unlinkOidc'], panel: 'app'));
            }

            $user = $login->resolve($identity, $intent === 'link' ? $current : null);
        } catch (OidcLoginException $exception) {
            return $this->failed(__($exception->getMessage()), $intent);
        } catch (Throwable $exception) {
            Log::warning('OIDC login failed', ['exception' => $exception->getMessage()]);

            return $this->failed(__('The sign-in failed. Please try again.'), $intent);
        }

        // HOUSEHOLDS_DRIVER=account-manager: pending memberships and the user's households (failures only log).
        $households->syncOnSignIn($user);

        // ENTITLEMENTS_DRIVER / AUTH_ADMIN_SOURCE=account-manager: the current snapshot (failures keep the last one).
        $entitlements->syncOnSignIn($user);

        if (! Entitlements::allowsAccess($user)) {
            if ($current) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $this->failed(Entitlements::deniedMessage($user), 'login');
        }

        // Remembered like Shoppinglist and Chorelist, otherwise the session ends after SESSION_LIFETIME idle minutes.
        // Also after linking: the remember token was rotated, this device gets the new one.
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $accounts->rememberSignIn();

        if ($intent === 'link') {
            Notification::make()->success()->title(__('The sign-in is linked to your account.'))->send();

            return redirect()->to(EditProfile::getUrl(panel: 'app'));
        }

        return redirect()->intended(Filament::getPanel('app')->getUrl());
    }

    private function provider(): AbstractProvider
    {
        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('oidc');

        $provider->redirectUrl(route('oidc.callback'));

        if (config('login.oidc.required_group')) {
            $provider->scopes(['groups']);
        }

        return $provider;
    }

    private function failed(string $message, string $intent): RedirectResponse
    {
        Notification::make()->danger()->title($message)->persistent()->send();

        return redirect()->to($intent === 'login'
            ? Filament::getPanel('app')->getLoginUrl()
            : EditProfile::getUrl(panel: 'app'));
    }
}
