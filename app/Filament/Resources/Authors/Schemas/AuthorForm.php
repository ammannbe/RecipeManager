<?php

namespace App\Filament\Resources\Authors\Schemas;

use App\Models\Author;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn (?User $record): bool => $record === null)
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        Toggle::make('admin')
                            ->label(__('Admin')),
                    ]),
            ]);
    }
}
