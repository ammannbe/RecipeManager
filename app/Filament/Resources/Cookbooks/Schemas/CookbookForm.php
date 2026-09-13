<?php

namespace App\Filament\Resources\Cookbooks\Schemas;

use App\Models\Cookbook;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class CookbookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(100)
                    ->unique(
                        Cookbook::class,
                        'name',
                        ignoreRecord: true,
                        // Scoped to the owner, and trashed cookbooks free their name again.
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                            ->where('author_id', user()?->admin ? $get('author_id') : user()?->author_id)
                            ->whereNull('deleted_at'),
                    ),
                Select::make('author_id')
                    ->label(__('Author'))
                    ->relationship('author', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    // Only admins may pick an owner; everyone else is pinned server-side.
                    ->visible(fn (): bool => (bool) user()?->admin)
                    ->dehydrated(fn (): bool => (bool) user()?->admin),
                Toggle::make('is_public')
                    ->label(__('Public'))
                    ->helperText(__('Publishing a cookbook makes every recipe in it, including its images, visible to everyone.'))
                    ->default(false),
            ]);
    }
}
