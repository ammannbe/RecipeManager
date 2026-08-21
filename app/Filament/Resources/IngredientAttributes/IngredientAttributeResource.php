<?php

namespace App\Filament\Resources\IngredientAttributes;

use App\Filament\Resources\IngredientAttributes\Pages\CreateIngredientAttribute;
use App\Filament\Resources\IngredientAttributes\Pages\EditIngredientAttribute;
use App\Filament\Resources\IngredientAttributes\Pages\ListIngredientAttributes;
use App\Filament\Resources\IngredientAttributes\Schemas\IngredientAttributeForm;
use App\Filament\Resources\IngredientAttributes\Schemas\IngredientAttributeInfolist;
use App\Filament\Resources\IngredientAttributes\Tables\IngredientAttributesTable;
use App\Models\IngredientAttribute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IngredientAttributeResource extends Resource
{
    protected static ?string $model = IngredientAttribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 7;

    public static function getModelLabel(): string
    {
        return __('Ingredient attribute');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ingredient attributes');
    }

    public static function form(Schema $schema): Schema
    {
        return IngredientAttributeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IngredientAttributeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IngredientAttributesTable::configure($table);
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
            'index' => ListIngredientAttributes::route('/'),
            'create' => CreateIngredientAttribute::route('/create'),
            'edit' => EditIngredientAttribute::route('/{record}/edit'),
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
        /** @var IngredientAttribute $record */
        return (bool) user()?->admin && $record->ingredients()->count() === 0;
    }
}
