<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateAuthor extends CreateRecord
{
    protected static string $resource = AuthorResource::class;

    public function getMaxContentWidth(): Width
    {
        return Width::TwoExtraLarge;
    }
}
