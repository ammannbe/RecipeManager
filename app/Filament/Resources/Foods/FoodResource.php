<?php

namespace App\Filament\Resources\Foods;

use App\Filament\Resources\Foods\Pages\CreateFood;
use App\Filament\Resources\Foods\Pages\EditFood;
use App\Filament\Resources\Foods\Pages\ListFoods;
use App\Filament\Resources\Foods\Schemas\FoodForm;
use App\Filament\Resources\Foods\Schemas\FoodInfolist;
use App\Filament\Resources\Foods\Tables\FoodsTable;
use App\Models\Food;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FoodResource extends Resource
{
    protected static ?string $model = Food::class;

    protected static ?string $slug = 'foods';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getModelLabel(): string
    {
        return __('Food');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Foods');
    }

    public static function form(Schema $schema): Schema
    {
        return FoodForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FoodInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FoodsTable::configure($table);
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
            'index' => ListFoods::route('/'),
            'create' => CreateFood::route('/create'),
            'edit' => EditFood::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function canCreate(): bool
    {
        return (bool) user()?->admin;
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) user()?->admin;
    }

    public static function canDelete(Model $record): bool
    {
        /** @var Food $record */
        return (bool) user()?->admin && $record->ingredients()->count() === 0;
    }
}
