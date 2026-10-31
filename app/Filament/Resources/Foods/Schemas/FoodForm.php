<?php

namespace App\Filament\Resources\Foods\Schemas;

use App\Services\Season;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FoodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                CheckboxList::make('season_months')
                    ->label(__('Fresh season'))
                    ->helperText(__('Months of fresh Swiss harvest (open field or unheated greenhouse).'))
                    ->options(Season::monthNames())
                    // Also on phones in columns (a plain number only applies from lg).
                    ->columns(['default' => 3, 'sm' => 4, 'lg' => 6])
                    ->gridDirection('row')
                    ->bulkToggleable()
                    ->columnSpanFull(),
                CheckboxList::make('storage_months')
                    ->label(__('From storage'))
                    ->helperText(__('Months available from Swiss storage only; not counted as in season.'))
                    ->options(Season::monthNames())
                    ->columns(['default' => 3, 'sm' => 4, 'lg' => 6])
                    ->gridDirection('row')
                    ->bulkToggleable()
                    ->columnSpanFull(),
            ]);
    }
}
