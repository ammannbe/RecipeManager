<?php

namespace App\Filament\Resources\Units\Tables;

use App\Filament\Resources\Concerns\LookupRequests;
use App\Filament\Resources\Units\UnitResource;
use App\Http\Middleware\SetLocale;
use App\Models\Unit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_shortcut')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('name_plural')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('name_plural_shortcut')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('base_amount')
                    ->label(__('Unit conversion'))
                    ->state(fn (Unit $record): ?string => $record->measure === null || $record->base_amount === null
                        ? null
                        : '1 '.($record->name_shortcut ?? $record->name).' = '.Number::format($record->base_amount, maxPrecision: 4, locale: SetLocale::numberLocale()).' '.$record->measure->baseUnit())
                    ->placeholder(__('per food')),
                LookupRequests::column(),
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
                TrashedFilter::make(),
            ])
            ->recordUrl(fn ($record): string => UnitResource::getUrl('edit', ['record' => $record]))
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
