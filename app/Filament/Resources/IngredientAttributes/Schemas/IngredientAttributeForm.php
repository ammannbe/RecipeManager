<?php

namespace App\Filament\Resources\IngredientAttributes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IngredientAttributeForm
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
