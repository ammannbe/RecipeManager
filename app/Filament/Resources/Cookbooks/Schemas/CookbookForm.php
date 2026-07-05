<?php

namespace App\Filament\Resources\Cookbooks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CookbookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                Select::make('author_id')
                    ->relationship('author', 'name')
                    ->required()
                    ->searchable()
                    ->default(fn (): ?int => user()?->author_id)
                    ->disabled(fn (): bool => ! (bool) user()?->admin)
                    ->dehydrated(),
            ]);
    }
}
