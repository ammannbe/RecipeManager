<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Pages\ImportRecipe;
use App\Filament\Resources\Recipes\RecipeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRecipes extends ListRecords
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label(__('Import recipe'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->url(ImportRecipe::getUrl()),
            CreateAction::make(),
        ];
    }
}
