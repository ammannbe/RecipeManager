<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Ai\AiException;
use App\Ai\RecipeAssistant;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Services\Document;
use App\Services\RecipeImport\RecipeJsonExporter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->map(fn (Document $document): array => [
                'path' => $record->getKey().'/'.$document->name(),
                'source' => $document->source(),
                'is_ai_generated' => $document->isAiGenerated(),
            ])
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
            ->filter(fn (mixed $photo): bool => is_array($photo) && ! empty($photo['path']))
            ->map(fn (array $photo): array => [
                'path' => basename($photo['path']),
                'source' => $photo['source'] ?? null,
                'is_ai_generated' => (bool) ($photo['is_ai_generated'] ?? false),
            ])
            ->unique('path')
            ->values();

        $existing = [];

        foreach ($record->photos as $document) {
            $existing[] = $document->name();
        }

        $removed = array_diff($existing, $incoming->pluck('path')->all());

        foreach ($removed as $filename) {
            Storage::disk('recipes')->delete($recordKey.'/'.$filename);
        }

        $data['photos'] = $incoming->all();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewFrontend')
                ->label(__('View'))
                ->url(fn (): string => route('recipes.show', $this->getRecord()))
                ->openUrlInNewTab(),
            Action::make('aiPhoto')
                ->label(__('Create photo with AI'))
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->visible(fn (): bool => RecipeAssistant::allows(user()))
                ->requiresConfirmation()
                ->modalDescription(__('The AI creates a photo of the dish from the name and ingredients. It is added to the photos and marked as AI generated. Unsaved changes are lost.'))
                ->action(function (RecipeAssistant $assistant): void {
                    /** @var Recipe $record */
                    $record = $this->getRecord();

                    set_time_limit(300);

                    try {
                        $assistant->image(user(), $record);
                    } catch (AiException $exception) {
                        Notification::make()->danger()->title(__($exception->getMessage()))->send();

                        return;
                    }

                    Notification::make()->success()->title(__('Photo created'))->send();

                    $this->redirect(RecipeResource::getUrl('edit', ['record' => $record]));
                }),
            Action::make('exportJson')
                ->label(__('Export as JSON'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (RecipeJsonExporter $exporter): StreamedResponse {
                    /** @var Recipe $record */
                    $record = $this->getRecord();

                    return response()->streamDownload(
                        fn () => print ($exporter->toJson($record)),
                        $exporter->filename($record),
                        ['Content-Type' => 'application/json'],
                    );
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
