<?php

namespace App\Filament\Resources\Cookbooks\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Pending invitations');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return user()?->can('share', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->modifyQueryUsing(fn ($query) => $query->pending())
            ->columns([
                TextColumn::make('email')
                    ->label(__('User email'))
                    ->searchable(),
                IconColumn::make('can_admin')->label(__('Administrate'))->boolean(),
                IconColumn::make('can_read')->label(__('Read'))->boolean(),
                IconColumn::make('can_create')->label(__('Create recipes'))->boolean(),
                IconColumn::make('can_update')->label(__('Edit recipes'))->boolean(),
                IconColumn::make('can_delete')->label(__('Delete recipes'))->boolean(),
                TextColumn::make('expires_at')
                    ->label(__('Expires at'))
                    ->dateTime(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label(__('Revoke')),
            ]);
    }
}
