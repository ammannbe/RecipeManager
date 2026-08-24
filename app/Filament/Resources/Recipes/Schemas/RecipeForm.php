<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Enums\Complexity;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\IngredientAttribute;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
            Select::make('ingredientAttributes')
                ->label(__('Attributes'))
                ->relationship('ingredientAttributes', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (): array => IngredientAttribute::query()->orderBy('name')->pluck('name', 'id')->all()),
        ];
    }

    /**
     * @return array<int, TableColumn>
     */
    private static function ingredientTableColumns(): array
    {
        return [
            TableColumn::make(__('Amount'))->width('6rem'),
            TableColumn::make(__('Amount max'))->width('6rem'),
            TableColumn::make(__('Unit'))->width('12rem'),
            TableColumn::make(__('Food'))->width('16rem'),
            TableColumn::make(__('Attributes'))->width('16rem'),
        ];
    }

    /**
     * Moves an ingredient between groups. Repeaters cannot hand items to each other in
     * form state, so the row is written straight to the database and the page reloaded.
     */
    private static function moveIngredientAction(): Action
    {
        return Action::make('moveToGroup')
            ->label(__('Move to group'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->modalHeading(__('Move to group'))
            ->modalDescription(__('The recipe is reloaded afterwards. Save other changes first, they would be lost.'))
            ->visible(fn (array $arguments): bool => self::ingredientFromItemKey($arguments) !== null)
            ->schema(fn (Repeater $component, array $arguments): array => [
                Select::make('ingredient_group_id')
                    ->label(__('Ingredient group'))
                    ->placeholder(__('Ingredients without group'))
                    ->default(fn (): ?int => self::ingredientFromItemKey($arguments)?->ingredient_group_id)
                    ->options(fn (): array => self::recipeOf($component)
                        ?->ingredientGroups()
                        ->orderBy('position')
                        ->pluck('name', 'id')
                        ->all() ?? []),
            ])
            ->action(function (array $data, array $arguments, Repeater $component): void {
                $ingredient = self::ingredientFromItemKey($arguments);
                $recipe = self::recipeOf($component);

                if ($ingredient === null || $recipe === null) {
                    return;
                }

                $groupId = $data['ingredient_group_id'] ?? null;
                $groupId = $groupId === null || $groupId === '' ? null : (int) $groupId;

                if ($groupId !== null && ! $recipe->ingredientGroups()->whereKey($groupId)->exists()) {
                    return;
                }

                $ingredient->setAttribute('ingredient_group_id', $groupId);
                $ingredient->setAttribute('position', self::nextPositionIn($recipe, $groupId));
                $ingredient->save();

                // Alternatives follow their parent; the observer only syncs them on save.
                $ingredient->ingredients()->update(['ingredient_group_id' => $groupId]);

                Notification::make()
                    ->success()
                    ->title(__('Ingredient moved'))
                    ->send();

                redirect(RecipeResource::getUrl('edit', ['record' => $recipe]));
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function ingredientFromItemKey(array $arguments): ?Ingredient
    {
        $item = $arguments['item'] ?? null;

        // Unsaved repeater items use a uuid key and have no row to move yet.
        if (! is_string($item) || ! str_starts_with($item, 'record-')) {
            return null;
        }

        return Ingredient::query()->find((int) substr($item, strlen('record-')));
    }

    private static function recipeOf(Repeater $component): ?Recipe
    {
        $record = $component->getRecord();

        return match (true) {
            $record instanceof Recipe => $record,
            $record instanceof IngredientGroup => $record->recipe,
            default => null,
        };
    }

    private static function nextPositionIn(Recipe $recipe, ?int $groupId): int
    {
        return (int) $recipe->ingredients()
            ->whereNull('ingredient_id')
            ->where('ingredient_group_id', $groupId)
            ->max('position') + 1;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('author_id')
                    ->label(__('Author'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => (bool) user()?->admin)
                    ->options(fn (): array => Author::query()->orderBy('name')->pluck('name', 'id')->all()),
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
                    ->label(__('Servings'))
                    ->numeric()
                    ->minValue(0)
                    ->step(0.5)
                    ->nullable(),
                TextInput::make('serving_type')
                    ->label(__('Serving type'))
                    ->maxLength(20)
                    ->nullable(),
                Select::make('complexity')
                    ->label(__('Complexity'))
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
                    ->label(__('Instructions'))
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
                    ->extraItemActions([self::moveIngredientAction()])
                    ->table(self::ingredientTableColumns())
                    ->schema(self::ingredientFields())
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
                                    ->extraItemActions([self::moveIngredientAction()])
                                    ->table(self::ingredientTableColumns())
                                    ->schema(self::ingredientFields())
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
