<?php

namespace App\Filament\Resources\Households\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * What is shared with the household (read-only; shares are managed on the cookbook).
 */
class CookbooksRelationManager extends RelationManager
{
    protected static string $relationship = 'cookbooks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Shared cookbooks');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('author_name')
                    ->label(__('Owner')),
                IconColumn::make('can_admin')->label(__('Administrate'))->boolean(),
                IconColumn::make('can_read')->label(__('Read'))->boolean(),
                IconColumn::make('can_create')->label(__('Create recipes'))->boolean(),
                IconColumn::make('can_update')->label(__('Edit recipes'))->boolean(),
                IconColumn::make('can_delete')->label(__('Delete recipes'))->boolean(),
            ])
            ->emptyStateHeading(__('No cookbooks shared yet'))
            ->emptyStateDescription(__('Share a cookbook with the household on the cookbook page.'));
    }
}
