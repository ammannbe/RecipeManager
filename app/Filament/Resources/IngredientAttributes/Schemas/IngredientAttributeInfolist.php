<?php

namespace App\Filament\Resources\IngredientAttributes\Schemas;

use App\Models\IngredientAttribute;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class IngredientAttributeInfolist
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
                    ->visible(fn (IngredientAttribute $record): bool => $record->trashed()),
            ]);
    }
}
