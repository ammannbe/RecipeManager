<?php

namespace App\Filament\Resources\Households;

use App\Enums\HouseholdRole;
use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Filament\Resources\Households\Pages\ViewHousehold;
use App\Filament\Resources\Households\RelationManagers\CookbooksRelationManager;
use App\Filament\Resources\Households\RelationManagers\InvitationsRelationManager;
use App\Filament\Resources\Households\RelationManagers\UsersRelationManager;
use App\Models\Household;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Local households (HOUSEHOLDS_DRIVER=local): every user sees the households they belong
 * to, admins manage members and invitations. Cookbooks are shared with a household on
 * the cookbook ("Shared with households").
 */
class HouseholdResource extends Resource
{
    protected static ?string $model = Household::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 80;

    public static function getModelLabel(): string
    {
        return __('Household');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Households');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(100),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label(__('Name')),
            TextEntry::make('role')
                ->label(__('Your role'))
                ->state(fn (Household $record): ?string => $record->roleOf(user())?->label())
                ->badge(),
            TextEntry::make('creator.author.name')
                ->label(__('Created by'))
                ->placeholder('-'),
            TextEntry::make('created_at')
                ->label(__('Created at'))
                ->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label(__('Your role'))
                    ->state(fn (Household $record): ?string => $record->roleOf(user())?->label())
                    ->badge()
                    ->color(fn (Household $record): string => $record->roleOf(user()) === HouseholdRole::Admin ? 'primary' : 'gray'),
                TextColumn::make('users_count')
                    ->label(__('Members'))
                    ->counts('users')
                    ->sortable(),
                TextColumn::make('cookbooks_count')
                    ->label(__('Cookbooks'))
                    ->counts('cookbooks')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading(__('No households yet'))
            ->emptyStateDescription(__('Create a household to share cookbooks with everyone in it.'));
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
            InvitationsRelationManager::class,
            CookbooksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHouseholds::route('/'),
            'view' => ViewHousehold::route('/{record}'),
        ];
    }

    /**
     * Only the user's own households, global admins included: households are private.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->scopes(['withMember' => [user()]]);
    }
}
