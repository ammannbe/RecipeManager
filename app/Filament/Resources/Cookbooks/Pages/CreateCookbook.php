<?php

namespace App\Filament\Resources\Cookbooks\Pages;

use App\Filament\Resources\Cookbooks\CookbookResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCookbook extends CreateRecord
{
    protected static string $resource = CookbookResource::class;
}
