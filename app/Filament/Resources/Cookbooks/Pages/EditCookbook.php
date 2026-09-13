<?php

namespace App\Filament\Resources\Cookbooks\Pages;

use App\Filament\Resources\Cookbooks\CookbookResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCookbook extends EditRecord
{
    protected static string $resource = CookbookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Never trust a submitted owner: only admins may reassign one.
        if (! user()?->admin) {
            unset($data['author_id']);
        }

        return $data;
    }
}
