<?php

namespace App\Filament\Resources\Cookbooks\Pages;

use App\Filament\Resources\Cookbooks\CookbookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCookbooks extends ListRecords
{
    protected static string $resource = CookbookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
