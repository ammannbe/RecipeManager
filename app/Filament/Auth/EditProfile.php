<?php

namespace App\Filament\Auth;

use App\Auth\OidcLabel;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;

class EditProfile extends BaseEditProfile
{
    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('linkOidc')
                ->label(fn (): string => __('Link :provider', ['provider' => OidcLabel::get()]))
                ->icon('heroicon-o-link')
                ->color('gray')
                ->url(fn (): string => route('oidc.redirect', ['link' => 1]))
                ->visible(fn (): bool => config('login.oidc.enabled') && $this->profileUser()->oidc_sub === null),
            Action::make('unlinkOidc')
                ->label(fn (): string => __('Unlink :provider', ['provider' => OidcLabel::get()]))
                ->icon('heroicon-o-link-slash')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('You can then only sign in with your password.'))
                // Without a password the account would no longer be reachable.
                ->visible(fn (): bool => $this->profileUser()->oidc_sub !== null && $this->profileUser()->password !== null)
                ->action(function (): void {
                    $this->profileUser()->forceFill(['oidc_sub' => null])->save();

                    Notification::make()->success()->title(__('The sign-in is unlinked.'))->send();
                }),
        ];
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
