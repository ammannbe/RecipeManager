<?php

namespace App\Filament\Resources\Foods\Schemas;

use App\Models\Food;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FoodInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Food $record): bool => $record->trashed()),
            ]);
    }
}
