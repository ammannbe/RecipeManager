<?php

namespace App\Filament\Resources\Cookbooks\Pages;

use App\Filament\Resources\Cookbooks\CookbookResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCookbook extends CreateRecord
{
    protected static string $resource = CookbookResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Never trust a submitted owner: only admins may choose one.
        if (! user()?->admin) {
            $data['author_id'] = user()?->author_id;
        }

        return $data;
    }
}
