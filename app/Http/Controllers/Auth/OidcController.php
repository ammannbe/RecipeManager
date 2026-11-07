<?php

namespace App\Http\Controllers\Auth;

use App\Auth\OidcLogin;
use App\Auth\OidcLoginException;
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
    public function redirect(Request $request): SymfonyRedirectResponse
    {
        abort_unless(config('login.oidc.enabled'), 404);

        // "Link" from the profile: the callback attaches the identity to the signed-in user.
        $request->session()->put('oidc.link', $request->boolean('link') && Auth::check());

        return $this->provider()->redirect();
    }

    public function callback(Request $request, OidcLogin $login, AccountManagerHouseholds $households): RedirectResponse
    {
        abort_unless(config('login.oidc.enabled'), 404);

        $linking = (bool) $request->session()->pull('oidc.link', false) && Auth::check();

        try {
            $identity = $this->provider()->user();

            if (! $identity instanceof AbstractUser) {
                throw new UnexpectedValueException('Unexpected OIDC user.');
            }

            /** @var User|null $current */
            $current = $linking ? Auth::user() : null;

            $user = $login->resolve($identity, $current);
        } catch (OidcLoginException $exception) {
            return $this->failed(__($exception->getMessage()), $linking);
        } catch (Throwable $exception) {
            Log::warning('OIDC login failed', ['exception' => $exception->getMessage()]);

            return $this->failed(__('The sign-in failed. Please try again.'), $linking);
        }

        // HOUSEHOLDS_DRIVER=account-manager: pending memberships and the user's households (failures only log).
        $households->syncOnSignIn($user);

        if ($linking) {
            Notification::make()->success()->title(__('The sign-in is linked to your account.'))->send();

            return redirect()->to(EditProfile::getUrl(panel: 'app'));
        }

        // Remembered like Shoppinglist and Chorelist, otherwise the session ends after SESSION_LIFETIME idle minutes.
        Auth::login($user, remember: true);
        $request->session()->regenerate();

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

    private function failed(string $message, bool $linking): RedirectResponse
    {
        Notification::make()->danger()->title($message)->persistent()->send();

        return redirect()->to($linking
            ? EditProfile::getUrl(panel: 'app')
            : Filament::getPanel('app')->getLoginUrl());
    }
}
