<?php

namespace App\Filament\Resources\Foods\Schemas;

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
            ]);
    }
}
