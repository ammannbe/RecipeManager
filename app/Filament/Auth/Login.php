<?php

namespace App\Filament\Auth;

use App\Auth\OidcAccount;
use App\Entitlements\Entitlements;
use App\Models\OAuthClient;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class Login extends BaseLogin
{
    /**
     * Set from "?local=1" on the first request; Livewire updates do not carry the query string.
     */
    #[Locked]
    public bool $localRequested = false;

    /**
     * Signing in to connect a trusted companion app (see connectsCompanionApp()).
     */
    #[Locked]
    public bool $companionApp = false;

    public function mount(): void
    {
        $this->localRequested = request()->boolean('local');
        $this->companionApp = self::connectsCompanionApp();

        // Signed out because the AccountManager blocked the access (EnsureEntitlementAccess).
        $blocked = Entitlements::messageFor((string) request()->query('access'));

        if ($blocked !== null) {
            Notification::make()->danger()->title($blocked)->persistent()->send();
        }

        parent::mount();
    }

    public function authenticate(): ?LoginResponse
    {
        if (! $this->showsLocalLogin()) {
            return null;
        }

        return parent::authenticate();
    }

    public function form(Schema $schema): Schema
    {
        if (! $this->showsLocalLogin()) {
            return $schema->components([]);
        }

        return parent::form($schema);
    }

    protected function getFormActions(): array
    {
        return $this->showsLocalLogin() ? parent::getFormActions() : [];
    }

    /**
     * With the local login disabled, only admins may sign in with a password (emergency access),
     * and everybody while connecting a companion app: accounts without OIDC sign-in would have no
     * way to connect otherwise. Accounts linked to the OIDC sign-in never sign in with a password
     * (they have none; this also covers a password left over by mistake).
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if ($user instanceof User && $user->isLinkedToOidc()) {
            return false;
        }

        if (! config('login.local.enabled') && ! $this->companionApp && ! ($user instanceof User && $user->isAdmin())) {
            return false;
        }

        return parent::isUserAllowedToAccessPanel($user);
    }

    /**
     * A linked account is told to use the provider. This only adds "linked or not" for an address
     * whose account exists: registration and password reset already tell that it exists, and the
     * login is rate limited like before.
     */
    protected function throwFailureValidationException(): never
    {
        $email = $this->data['email'] ?? null;

        if (is_string($email) && $email !== '' && User::query()->where('email', $email)->whereNotNull('oidc_sub')->exists()) {
            throw ValidationException::withMessages([
                'data.email' => OidcAccount::message(),
            ]);
        }

        parent::throwFailureValidationException();
    }

    /**
     * The password form is hidden when only the OIDC login is offered; "?local=1" shows it for admins.
     * Connecting a companion app always shows it.
     */
    public function showsLocalLogin(): bool
    {
        return config('login.local.enabled')
            || ! config('login.oidc.enabled')
            || $this->localRequested
            || $this->companionApp;
    }

    /**
     * The guest was sent here from the OAuth approval of a trusted companion app (e.g. Shoppinglist)
     * and returns there after signing in.
     */
    public static function connectsCompanionApp(): bool
    {
        $intended = session('url.intended');

        if (! is_string($intended) || trim((string) parse_url($intended, PHP_URL_PATH), '/') !== 'oauth/authorize') {
            return false;
        }

        parse_str((string) parse_url($intended, PHP_URL_QUERY), $query);

        return OAuthClient::isTrusted(is_string($query['client_id'] ?? null) ? $query['client_id'] : null);
    }
}
