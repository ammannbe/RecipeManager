<?php

namespace App\Filament\Resources\Foods\Schemas;

use App\Enums\Measure;
use App\Models\Unit;
use App\Services\Season;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                Section::make(__('Unit conversion'))
                    ->description(__('Used by "Convert" on the recipe page and for shopping lists. Values are averages.'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('measure')
                            ->label(__('Convert to'))
                            ->helperText(__('Empty: amounts keep their measure (EL becomes ml).'))
                            ->options(Measure::options()),
                        TextInput::make('density')
                            ->label(__('Density (g per ml)'))
                            ->helperText(__('Needed between weight and volume, e.g. oil 0.92, flour 0.55.'))
                            ->numeric()
                            ->minValue(0.001)
                            ->maxValue(99),
                        Repeater::make('unitWeights')
                            ->label(__('Weight per piece'))
                            ->relationship()
                            ->schema([
                                Select::make('unit_id')
                                    ->label(__('Unit'))
                                    ->options(fn (): array => Unit::query()->whereNull('measure')->orderBy('name')->pluck('name', 'id')->all())
                                    ->required()
                                    ->distinct(),
                                TextInput::make('grams')
                                    ->label(__('Grams'))
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel(__('Add weight'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
