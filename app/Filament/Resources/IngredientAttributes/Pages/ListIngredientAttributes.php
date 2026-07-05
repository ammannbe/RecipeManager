<?php

namespace App\Filament\Resources\IngredientAttributes\Pages;

use App\Filament\Resources\IngredientAttributes\IngredientAttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIngredientAttributes extends ListRecords
{
    protected static string $resource = IngredientAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
