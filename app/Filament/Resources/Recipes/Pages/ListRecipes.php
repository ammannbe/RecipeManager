<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Pages\ImportRecipe;
use App\Filament\Resources\Recipes\RecipeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ListRecipes extends ListRecords
{
    public const TRASH_TAB = 'trash';

    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label(__('Import recipe'))
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->url(ImportRecipe::getUrl()),
            CreateAction::make(),
        ];
    }

    /**
     * Both tabs keep the visibility of RecipeResource::getEloquentQuery().
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('All')),
            self::TRASH_TAB => Tab::make(__('Trash'))
                ->icon(Heroicon::OutlinedTrash)
                ->badge(fn (): ?int => self::onlyTrashed(RecipeResource::getEloquentQuery())->count() ?: null)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => self::onlyTrashed($query)),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function onlyTrashed(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SoftDeletingScope::class)->whereNotNull('recipes.deleted_at');
    }
}
