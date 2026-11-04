<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Cookbooks\CookbookResource;
use App\Models\Cookbook;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cookbooks the user owns or is a member of, with their number of recipes.
 */
class MyCookbooksWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('My cookbooks');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Cookbook::query()
                ->where(fn (Builder $query) => $query
                    ->where('author_id', user()?->author_id)
                    ->orWhereHas('members', fn (Builder $member) => $member->whereKey(user()?->getKey())))
                ->withCount('recipes'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('Name')),
                TextColumn::make('recipes_count')->label(__('Recipes'))->numeric(),
                IconColumn::make('is_public')->label(__('Public'))->boolean(),
            ])
            ->recordUrl(fn (Cookbook $record): string => CookbookResource::getUrl('edit', ['record' => $record]))
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('No cookbooks yet'));
    }
}
