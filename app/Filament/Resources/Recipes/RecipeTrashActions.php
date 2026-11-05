<?php

namespace App\Filament\Resources\Recipes;

use App\Models\Recipe;
use App\Services\RecipeNames;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Restoring a trashed recipe, in the list and on the edit page. A live recipe may have taken its
 * name meanwhile (only possible without cookbook, see RecipeNames): then "Restore" explains the
 * conflict and "Restore under another name" offers a free name.
 */
class RecipeTrashActions
{
    public static function restoreAction(): RestoreAction
    {
        return RestoreAction::make()
            ->before(function (RestoreAction $action, Recipe $record): void {
                if (app(RecipeNames::class)->liveDuplicateOf($record) === null) {
                    return;
                }

                Notification::make()
                    ->danger()
                    ->title(__('Recipe could not be restored'))
                    ->body(__('Another recipe named ":name" exists. Restore this one under another name.', ['name' => $record->name]))
                    ->persistent()
                    ->send();

                $action->cancel();
            });
    }

    public static function restoreUnderAnotherNameAction(): Action
    {
        return Action::make('restoreUnderAnotherName')
            ->label(__('Restore under another name'))
            ->icon(Heroicon::ArrowUturnLeft)
            ->color('gray')
            ->authorize('restore')
            ->visible(fn (Recipe $record): bool => $record->trashed() && app(RecipeNames::class)->liveDuplicateOf($record) !== null)
            ->modalHeading(fn (Recipe $record): string => __('Restore ":name" under another name', ['name' => $record->name]))
            ->modalDescription(fn (Recipe $record): string => __('Another recipe named ":name" exists.', ['name' => $record->name]))
            ->fillForm(fn (Recipe $record): array => [
                'name' => app(RecipeNames::class)->availableName($record->name, $record->cookbook_id, $record->author_id, $record->id),
            ])
            ->schema(fn (Recipe $record): array => [self::nameInput($record, ignoreId: $record->id)])
            ->action(function (Recipe $record, array $data): void {
                $record->name = trim((string) $data['name']);
                $record->restore();

                Notification::make()->success()->title(__('Recipe restored'))->body($record->name)->send();
            });
    }

    /**
     * A new name for the recipe, free in its cookbook (also against the trash).
     */
    public static function nameInput(Recipe $recipe, ?int $ignoreId = null): TextInput
    {
        return TextInput::make('name')
            ->label(__('Name'))
            ->required()
            ->maxLength(RecipeNames::MAX_LENGTH)
            ->rules([fn (): Closure => app(RecipeNames::class)->rule($recipe->cookbook_id, $recipe->author_id, $ignoreId)]);
    }
}
