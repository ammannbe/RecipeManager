<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Attributes\Locked;

class Login extends BaseLogin
{
    /**
     * Set from "?local=1" on the first request; Livewire updates do not carry the query string.
     */
    #[Locked]
    public bool $localRequested = false;

    public function mount(): void
    {
        $this->localRequested = request()->boolean('local');

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
     * With the local login disabled, only admins may sign in with a password (emergency access).
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if (! config('login.local.enabled') && ! ($user instanceof User && $user->admin)) {
            return false;
        }

        return parent::isUserAllowedToAccessPanel($user);
    }

    /**
     * The password form is hidden when only the OIDC login is offered; "?local=1" shows it for admins.
     */
    public function showsLocalLogin(): bool
    {
        return config('login.local.enabled')
            || ! config('login.oidc.enabled')
            || $this->localRequested;
    }
}
