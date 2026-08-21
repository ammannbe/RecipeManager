<?php

namespace App\Filament\Resources\Cookbooks;

use App\Filament\Resources\Cookbooks\Pages\CreateCookbook;
use App\Filament\Resources\Cookbooks\Pages\EditCookbook;
use App\Filament\Resources\Cookbooks\Pages\ListCookbooks;
use App\Filament\Resources\Cookbooks\Schemas\CookbookForm;
use App\Filament\Resources\Cookbooks\Schemas\CookbookInfolist;
use App\Filament\Resources\Cookbooks\Tables\CookbooksTable;
use App\Models\Cookbook;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CookbookResource extends Resource
{
    protected static ?string $model = Cookbook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Cookbook');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Cookbooks');
    }

    public static function form(Schema $schema): Schema
    {
        return CookbookForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CookbookInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CookbooksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCookbooks::route('/'),
            'create' => CreateCookbook::route('/create'),
            'edit' => EditCookbook::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = user();

        $query = parent::getEloquentQuery()->with('author');

        if ($user?->admin) {
            return $query;
        }

        return $query->where('author_id', $user?->author_id);
    }
}
