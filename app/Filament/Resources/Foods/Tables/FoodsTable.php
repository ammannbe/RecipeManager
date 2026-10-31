<?php

namespace App\Filament\Resources\Foods\Tables;

use App\Filament\Resources\Concerns\LookupRequests;
use App\Filament\Resources\Foods\FoodResource;
use App\Models\Food;
use App\Services\Season;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FoodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                LookupRequests::column(),
                TextColumn::make('season')
                    ->label(__('Season'))
                    ->state(fn (Food $record): string => Season::summary($record->season_months))
                    ->description(fn (Food $record): ?string => $record->storage_months === null
                        ? null
                        : __('Storage').': '.Season::summary($record->storage_months))
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                LookupRequests::filter(),
                TernaryFilter::make('season_data')
                    ->label(__('Season data'))
                    ->trueLabel(__('With season data'))
                    ->falseLabel(__('Without season data'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(
                            fn (Builder $query): Builder => $query->whereNotNull('season_months')->orWhereNotNull('storage_months'),
                        ),
                        false: fn (Builder $query): Builder => $query->whereNull('season_months')->whereNull('storage_months'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TrashedFilter::make(),
            ])
            ->recordUrl(fn ($record): string => FoodResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                LookupRequests::approveAction(),
                LookupRequests::mergeAction(),
                EditAction::make(),
                // Same rules as on the edit page: the policy only allows deleting unused entries.
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                    ForceDeleteBulkAction::make()->authorizeIndividualRecords('forceDelete'),
                    RestoreBulkAction::make()->authorizeIndividualRecords('restore'),
                ]),
            ]);
    }
}
