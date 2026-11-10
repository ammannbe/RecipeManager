<?php

namespace App\Filament\Resources\Authors\Schemas;

use App\Ai\RecipeAssistant;
use App\Auth\OidcLabel;
use App\Entitlements\Entitlements;
use App\Households\AccountManagerLabel;
use App\Models\Author;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(50)
                    ->unique(Author::class, 'name', ignoreRecord: true),
                Toggle::make('has_user_account')
                    ->label(__('Has user account'))
                    ->live()
                    ->dehydrated(false)
                    ->formatStateUsing(fn (?Author $record): bool => (bool) $record?->user()->exists()),
                Section::make(__('User account'))
                    ->columns(1)
                    ->relationship('user', fn (Get $get): bool => (bool) $get('has_user_account'))
                    ->visible(fn (Get $get): bool => (bool) $get('has_user_account'))
                    // Without this the relationship is skipped when the toggle hides the section, so the user is never removed.
                    ->saveRelationshipsWhenHidden()
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                        ...$data,
                        'email_verified_at' => now(),
                    ])
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignoreRecord: true),
                        // Linked accounts sign in through the OIDC provider only (App\Auth\OidcAccount).
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn (?User $record): bool => $record === null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->hidden(fn (?User $record): bool => (bool) $record?->isLinkedToOidc()),
                        TextEntry::make('oidcSignIn')
                            ->label(__('Sign-in'))
                            ->state(fn (): string => __('Connected with :provider', ['provider' => OidcLabel::get()]))
                            ->visible(fn (?User $record): bool => (bool) $record?->isLinkedToOidc()),
                        // AUTH_ADMIN_SOURCE=account-manager: linked users get the admin rights from the AccountManager.
                        Toggle::make('admin')
                            ->label(__('Admin'))
                            ->disabled(fn (?User $record): bool => Entitlements::adminFromAccountManager() && (bool) $record?->isLinkedToOidc())
                            ->helperText(fn (?User $record): ?string => Entitlements::adminFromAccountManager() && $record?->isLinkedToOidc()
                                ? (string) __('Admin rights of linked accounts are managed in “:name”.', ['name' => AccountManagerLabel::get()])
                                : null),
                        // ENTITLEMENTS_DRIVER=account-manager: linked users get the AI features (recipes.ai) from the AccountManager.
                        Toggle::make('ai_enabled')
                            ->label(__('AI features'))
                            ->disabled(fn (?User $record): bool => RecipeAssistant::managedByAccountManager($record))
                            ->helperText(fn (?User $record): string => RecipeAssistant::managedByAccountManager($record)
                                ? (string) __('AI features of linked accounts are managed in “:name”.', ['name' => AccountManagerLabel::get()])
                                : (string) __('Create recipes and photos with AI (verified users only).'))
                            ->default(true),
                    ]),
            ]);
    }
}
