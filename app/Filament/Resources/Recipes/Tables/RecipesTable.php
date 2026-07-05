<?php

namespace App\Filament\Resources\Recipes\Tables;

use App\Enums\Complexity;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Recipe;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('complexity')
                    ->badge()
                    ->formatStateUsing(fn (?Complexity $state): string => $state?->label() ?? '-')
                    ->color(fn (?Complexity $state): string => $state?->color() ?? 'gray')
                    ->sortable(),
                TextColumn::make('author.name')
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => (bool) user()?->admin),
                TextColumn::make('cookbook.name')
                    ->label(__('Cookbook'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('preparation_time')
                    ->label(__('Time'))
                    ->time('H:i')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('ratings_avg_stars')
                    ->label(__('Stars'))
                    ->numeric(decimalPlaces: 1)
                    ->placeholder('0.0')
                    ->sortable(),
                TextColumn::make('ratings_count')
                    ->label(__('Ratings'))
                    ->sortable(),
            ])
            ->filters([
                Filter::make('quick')
                    ->label(__('Max. 30 min'))
                    ->query(fn (Builder $query): Builder => $query->where('preparation_time', '<=', '00:30:00')),
                SelectFilter::make('complexity')
                    ->options([
                        Complexity::Simple->value => Complexity::Simple->label(),
                        Complexity::Normal->value => Complexity::Normal->label(),
                        Complexity::Difficult->value => Complexity::Difficult->label(),
                    ]),
                SelectFilter::make('category_id')
                    ->label(__('Category'))
                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('author_id')
                    ->label(__('Author'))
                    ->visible(fn (): bool => (bool) user()?->admin)
                    ->options(fn (): array => Author::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('cookbook_id')
                    ->label(__('Cookbook'))
                    ->options(function (): array {
                        if (user()?->admin) {
                            return Cookbook::query()->orderBy('name')->pluck('name', 'id')->all();
                        }

                        return Cookbook::query()
                            ->where('author_id', user()?->author_id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    }),
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (Recipe $record): string => RecipeResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Recipe $record): string => route('recipes.show', $record)),
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
