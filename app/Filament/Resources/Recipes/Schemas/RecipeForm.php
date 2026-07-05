<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Enums\Complexity;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\Unit;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecipeForm
{
    /**
     * @return array<int, Field>
     */
    private static function ingredientFields(): array
    {
        return [
            TextInput::make('amount')
                ->numeric()
                ->step(0.01)
                ->nullable(),
            TextInput::make('amount_max')
                ->numeric()
                ->step(0.01)
                ->nullable(),
            Select::make('unit_id')
                ->label(__('Unit'))
                ->searchable()
                ->preload()
                ->nullable()
                ->options(fn (): array => Unit::query()->orderBy('name')->pluck('name', 'id')->all()),
            Select::make('food_id')
                ->label(__('Food'))
                ->required()
                ->searchable()
                ->preload()
                ->options(fn (): array => Food::query()->orderBy('name')->pluck('name', 'id')->all()),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cookbook_id')
                    ->label(__('Cookbook'))
                    ->nullable()
                    ->searchable()
                    ->preload()
                    ->options(function (): array {
                        if (user()?->admin) {
                            return Cookbook::query()->orderBy('name')->pluck('name', 'id')->all();
                        }

                        return Cookbook::query()
                            ->where('author_id', user()?->author_id)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        return Cookbook::query()->create([
                            'name' => $data['name'],
                            'author_id' => user()->author_id,
                        ])->id;
                    }),
                Select::make('category_id')
                    ->label(__('Category'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        return Category::query()->create([
                            'name' => $data['name'],
                        ])->id;
                    }),
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                TextInput::make('servings')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.5)
                    ->nullable(),
                TextInput::make('serving_type')
                    ->maxLength(20)
                    ->nullable(),
                Select::make('complexity')
                    ->required()
                    ->options([
                        Complexity::Simple->value => Complexity::Simple->label(),
                        Complexity::Normal->value => Complexity::Normal->label(),
                        Complexity::Difficult->value => Complexity::Difficult->label(),
                    ]),
                TimePicker::make('preparation_time')
                    ->label(__('Preparation time'))
                    ->seconds(false)
                    ->nullable(),
                RichEditor::make('instructions')
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('photos')
                    ->label(__('Images'))
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->openable()
                    ->downloadable()
                    ->disk('recipes')
                    ->directory(fn ($record): ?string => $record ? (string) $record->getKey() : null)
                    ->visibility('public')
                    ->visibleOn('edit')
                    ->columnSpanFull(),
                Repeater::make('ungroupedIngredients')
                    ->label(__('Ingredients without group'))
                    ->relationship('ungroupedIngredients')
                    ->orderColumn('position')
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                        $data['ingredient_group_id'] = null;

                        return $data;
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                        $data['ingredient_group_id'] = null;

                        return $data;
                    })
                    ->reorderable()
                    ->collapsible()
                    ->schema(self::ingredientFields())
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make(__('Ingredient groups'))
                    ->schema([
                        Repeater::make('ingredientGroups')
                            ->label(__('Ingredient groups'))
                            ->relationship()
                            ->orderColumn('position')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(20),
                                Repeater::make('ingredients')
                                    ->label(__('Ingredients'))
                                    ->relationship()
                                    ->orderColumn('position')
                                    ->reorderable()
                                    ->collapsible()
                                    ->schema(self::ingredientFields())
                                    ->columns(4)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
