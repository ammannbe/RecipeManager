<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Models\Recipe;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RecipeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('cookbook.name')
                    ->label('Cookbook')
                    ->placeholder('-'),
                TextEntry::make('category.name')
                    ->label('Category'),
                TextEntry::make('author.name')
                    ->label('Author'),
                TextEntry::make('name'),
                TextEntry::make('servings')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('serving_type')
                    ->placeholder('-'),
                TextEntry::make('complexity')
                    ->badge(),
                TextEntry::make('instructions')
                    ->columnSpanFull(),
                TextEntry::make('photos')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('preparation_time')
                    ->time()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Recipe $record): bool => $record->trashed()),
            ]);
    }
}
