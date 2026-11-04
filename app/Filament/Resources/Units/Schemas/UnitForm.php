<?php

namespace App\Filament\Resources\Units\Schemas;

use App\Enums\Measure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('name_shortcut'),
                TextInput::make('name_plural'),
                TextInput::make('name_plural_shortcut'),
                Select::make('measure')
                    ->label(__('Measure'))
                    ->helperText(__('Leave empty for pieces (Stück, Bund, …): their weight is set per food.'))
                    ->options(Measure::options())
                    ->live(),
                TextInput::make('base_amount')
                    ->label(fn (Get $get): string => $get('measure') === Measure::Volume->value ? __('Milliliters per unit') : __('Grams per unit'))
                    ->numeric()
                    ->minValue(0.0001)
                    ->requiredWith('measure')
                    ->visible(fn (Get $get): bool => filled($get('measure'))),
            ]);
    }
}
