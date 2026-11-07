<?php

namespace App\Filament\Auth;

use App\Auth\OidcAccount;
use App\Auth\OidcLabel;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

/**
 * Linked accounts (users.oidc_sub) show "Connected with …" instead of the password fields and can
 * only unlink together with a new password, after a recent OIDC sign-in. Unlinked accounts keep
 * the password fields and can link the sign-in after confirming their password (App\Auth\OidcAccount).
 */
class EditProfile extends BaseEditProfile
{
    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->linkOidcAction(),
            $this->unlinkOidcAction(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        if (! $this->profileUser()->isLinkedToOidc()) {
            return parent::form($schema);
        }

        $provider = OidcLabel::get();

        return $schema->components([
            $this->getNameFormComponent(),
            // Changing it needs the current password (which a linked account has not).
            TextInput::make('email')
                ->label(__('filament-panels::auth/pages/edit-profile.form.email.label'))
                ->email()
                ->disabled()
                ->belowContent(__('To change it, unlink :provider first.', ['provider' => $provider])),
            TextEntry::make('oidcSignIn')
                ->label(__('Sign-in'))
                ->state(__('Connected with :provider', ['provider' => $provider]))
                ->icon('heroicon-o-link')
                ->belowContent(__('Signing in with a password is turned off for this account.')),
        ]);
    }

    private function linkOidcAction(): Action
    {
        return Action::make('linkOidc')
            ->label(fn (): string => __('Link :provider', ['provider' => OidcLabel::get()]))
            ->icon('heroicon-o-link')
            ->color('gray')
            ->visible(fn (): bool => config('login.oidc.enabled') && ! $this->profileUser()->isLinkedToOidc())
            ->modalDescription(fn (): string => __('You then sign in with :provider only: your password is removed and your other devices are signed out.', ['provider' => OidcLabel::get()]))
            ->modalSubmitActionLabel(fn (): string => __('Continue with :provider', ['provider' => OidcLabel::get()]))
            // Linking removes the password, so a session alone (e.g. an unlocked device) is not enough.
            ->schema(fn (): array => $this->profileUser()->password === null ? [] : [
                TextInput::make('currentPassword')
                    ->label(__('filament-panels::auth/pages/edit-profile.form.current_password.label'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->autocomplete('current-password')
                    ->currentPassword(guard: Filament::getAuthGuard())
                    ->required(),
            ])
            ->rateLimit(5)
            ->action(function (): void {
                app(OidcAccount::class)->rememberPasswordConfirmation();

                $this->redirect(route('oidc.redirect', ['link' => 1]));
            });
    }

    /**
     * Without a recent OIDC sign-in the action first sends the user to the provider
     * (OidcController "confirm"), which returns here with the action opened.
     */
    private function unlinkOidcAction(): Action
    {
        $confirmed = fn (): bool => app(OidcAccount::class)->hasRecentSignIn();

        return Action::make('unlinkOidc')
            ->label(fn (): string => __('Unlink :provider', ['provider' => OidcLabel::get()]))
            ->icon('heroicon-o-link-slash')
            ->color('gray')
            // Without the OIDC login there is nothing to confirm with; users:unlink-oidc is the way back then.
            ->visible(fn (): bool => config('login.oidc.enabled') && $this->profileUser()->isLinkedToOidc())
            ->modalDescription(fn (): string => $confirmed()
                ? __('Choose a password. You then sign in with your e-mail address and this password instead of :provider.', ['provider' => OidcLabel::get()])
                : __('For your security, confirm with :provider first. You then choose a new password.', ['provider' => OidcLabel::get()]))
            ->modalSubmitActionLabel(fn (): string => $confirmed()
                ? __('Unlink')
                : __('Continue with :provider', ['provider' => OidcLabel::get()]))
            ->schema(fn (): array => ! $confirmed() ? [] : [
                TextInput::make('password')
                    ->label(__('filament-panels::auth/pages/edit-profile.form.password.label'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->rule(Password::default())
                    ->showAllValidationMessages()
                    ->autocomplete('new-password')
                    ->same('passwordConfirmation'),
                TextInput::make('passwordConfirmation')
                    ->label(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.label'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->autocomplete('new-password')
                    ->dehydrated(false),
            ])
            ->action(function (array $data) use ($confirmed): void {
                if (! $confirmed() || ! is_string($data['password'] ?? null)) {
                    $this->redirect(route('oidc.redirect', ['confirm' => 1]));

                    return;
                }

                app(OidcAccount::class)->unlink($this->profileUser(), $data['password']);

                Notification::make()->success()->title(__('The sign-in is unlinked. You now sign in with your e-mail address and password.'))->send();

                $this->redirect(static::getUrl());
            });
    }

    private function profileUser(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getUser();

        $data['name'] = $user->author?->name;

        return $data;
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label(__('Name'))
            ->required()
            ->maxLength(50)
            ->unique('authors', 'name', ignorable: function () {
                /** @var User $user */
                $user = $this->getUser();

                return $user->author;
            })
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $name = trim((string) ($data['name'] ?? ''));

        unset($data['name']);

        parent::handleRecordUpdate($record, $data);

        if ($record instanceof User && $record->author) {
            $record->author->update(['name' => $name]);
        }

        return $record;
    }
}
