<?php

namespace App\Filament\Resources\Authors\Tables;

use App\Auth\OidcLabel;
use App\Filament\Resources\Authors\AuthorResource;
use App\Models\Author;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AuthorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label(__('User email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sign_in')
                    ->label(__('Sign-in'))
                    ->badge()
                    ->color(fn (Author $record): string => $record->user?->isLinkedToOidc() ? 'primary' : 'gray')
                    ->state(fn (Author $record): ?string => match (true) {
                        $record->user === null => null,
                        $record->user->isLinkedToOidc() => OidcLabel::get(),
                        default => (string) __('Password'),
                    }),
                IconColumn::make('user.admin')
                    ->label(__('Admin'))
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordUrl(fn ($record): string => AuthorResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
