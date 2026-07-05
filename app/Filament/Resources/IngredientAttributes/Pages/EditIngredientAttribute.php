<?php

namespace App\Filament\Resources\IngredientAttributes\Pages;

use App\Filament\Resources\IngredientAttributes\IngredientAttributeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditIngredientAttribute extends EditRecord
{
    protected static string $resource = IngredientAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
