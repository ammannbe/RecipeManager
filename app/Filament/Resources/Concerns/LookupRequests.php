<?php

namespace App\Filament\Resources\Concerns;

use App\Models\Food;
use App\Models\IngredientAttribute;
use App\Models\Tag;
use App\Models\Unit;
use App\Services\Lookup\LookupMerger;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Requested foods, units, attributes and tags (see Requestable) in their admin tables:
 * column, filter and the admin actions "Approve" and "Merge into …".
 */
class LookupRequests
{
    public static function column(): TextColumn
    {
        return TextColumn::make('requester.name')
            ->label(__('Requested by'))
            ->badge()
            ->color('warning')
            ->placeholder('-')
            ->toggleable();
    }

    public static function filter(): TernaryFilter
    {
        return TernaryFilter::make('requested')
            ->label(__('Requested'))
            ->queries(
                true: fn (Builder $query) => $query->whereNotNull('requested_at'),
                false: fn (Builder $query) => $query->whereNull('requested_at'),
            );
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (Food|Unit|IngredientAttribute|Tag $record): bool => (bool) user()?->admin && $record->isRequested())
            ->action(function (Food|Unit|IngredientAttribute|Tag $record): void {
                $record->approve();
                Notification::make()->success()->title(__('Approved'))->send();
            });
    }

    public static function mergeAction(): Action
    {
        return Action::make('merge')
            ->label(__('Merge into …'))
            ->icon(Heroicon::OutlinedArrowsPointingIn)
            ->visible(fn (): bool => (bool) user()?->admin)
            ->modalDescription(__('All recipes using this entry will use the chosen one instead. This entry is then deleted.'))
            ->schema(fn (Model $record): array => [
                Select::make('target')
                    ->label(__('Merge into'))
                    ->required()
                    ->searchable()
                    ->options(fn (): array => $record::query()
                        ->whereKeyNot($record->getKey())
                        ->whereNull('requested_at')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->action(function (Model $record, array $data): void {
                $target = $record::query()->whereKey($data['target'])->firstOrFail();
                app(LookupMerger::class)->merge($record, $target);
                Notification::make()->success()->title(__('Merged into ":name"', ['name' => $target->getAttribute('name')]))->send();
            });
    }

    /**
     * Number of open requests for the navigation badge (admins only).
     *
     * @param  class-string<Model>  $model
     */
    public static function badge(string $model): ?string
    {
        if (! user()?->admin) {
            return null;
        }

        $count = $model::query()->whereNotNull('requested_at')->count();

        return $count > 0 ? (string) $count : null;
    }
}
