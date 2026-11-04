<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The user's own recipes, last changed first.
 */
class RecentRecipesWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('My recently changed recipes');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Recipe::query()->where('author_id', user()?->author_id)->with('category'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->wrap(),
                TextColumn::make('category.name')->label(__('Category'))->toggleable(),
                IconColumn::make('is_public')->label(__('Public'))->boolean(),
                TextColumn::make('updated_at')->label(__('Changed'))->since(),
            ])
            ->recordUrl(fn (Recipe $record): string => RecipeResource::getUrl('edit', ['record' => $record]))
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('No own recipes yet'));
    }
}
