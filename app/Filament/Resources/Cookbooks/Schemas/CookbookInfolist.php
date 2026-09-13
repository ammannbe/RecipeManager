<?php

namespace App\Filament\Resources\Cookbooks\Schemas;

use App\Models\Cookbook;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CookbookInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('author.name')
                    ->label('Author'),
                IconEntry::make('is_public')
                    ->label(__('Public'))
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Cookbook $record): bool => $record->trashed()),
            ]);
    }
}
