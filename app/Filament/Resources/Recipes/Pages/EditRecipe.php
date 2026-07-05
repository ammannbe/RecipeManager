<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Services\Document;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class EditRecipe extends EditRecord
{
    protected static string $resource = RecipeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Recipe $record */
        $record = $this->getRecord();

        $data['photos'] = $record->photos
            ->map(fn (Document $document): string => $record->getKey().'/'.$document->name())
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Recipe $record */
        $record = $this->getRecord();
        $recordKey = (string) $record->getKey();

        $incoming = collect(Arr::wrap($data['photos'] ?? []))
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => basename($path))
            ->unique()
            ->values()
            ->all();

        $existing = [];

        foreach ($record->photos as $document) {
            $existing[] = $document->name();
        }

        $removed = array_diff($existing, $incoming);

        foreach ($removed as $filename) {
            Storage::disk('recipes')->delete($recordKey.'/'.$filename);
        }

        $data['photos'] = $incoming;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewFrontend')
                ->label(__('View'))
                ->url(fn (): string => route('recipes.show', $this->getRecord()))
                ->openUrlInNewTab(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
