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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class RecipeForm
{
    /**
     * The fields describing a single ingredient row.
     *
     * @param  bool  $withAttributeRelationship  False inside the alternatives modal, which
     *                                           is detached from any ingredient record.
     * @return array<int, Field>
     */
    private static function ingredientBaseFields(bool $withAttributeRelationship = true): array
    {
        return [
            TextInput::make('amount')
                ->label(__('Amount'))
                ->numeric()
                ->step(0.01)
                ->nullable(),
            TextInput::make('amount_max')
                ->label(__('Amount max'))
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
                ->when(
                    $withAttributeRelationship,
                    fn (Select $select): Select => $select->relationship('ingredientAttributes', 'name'),
                )
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (): array => IngredientAttribute::query()->orderBy('name')->pluck('name', 'id')->all()),
        ];
    }

    /**
     * @return array<int, Field>
     */
    private static function ingredientFields(): array
    {
        return [
            ...self::ingredientBaseFields(),
            // Carries the alternatives edited in the modal until the form is saved.
            // A table repeater gives every other component its own cell, so this has
            // to be a Hidden field, which it renders without consuming a column.
            Hidden::make('alternatives')
                ->dehydrated(false)
                ->afterStateHydrated(function (Hidden $component, ?Ingredient $record): void {
                    $component->state(self::alternativesOf($record));
                })
                ->saveRelationshipsUsing(function (Hidden $component, ?Ingredient $record): void {
                    if ($record === null) {
                        return;
                    }

                    $state = $component->getState();

                    if (! is_array($state)) {
                        return;
                    }

                    self::syncAlternatives($record, $state);
                }),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function alternativesOf(?Ingredient $ingredient): array
    {
        return $ingredient
            ?->ingredients()
            ->with('ingredientAttributes')
            ->get()
            ->map(fn (Ingredient $alternative): array => [
                'id' => $alternative->id,
                'amount' => $alternative->amount,
                'amount_max' => $alternative->amount_max,
                'unit_id' => $alternative->unit_id,
                'food_id' => $alternative->food_id,
                'ingredientAttributes' => $alternative->ingredientAttributes->pluck('id')->all(),
            ])
            ->all() ?? [];
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
     * Edits an ingredient's alternatives in a modal, because a nested repeater cannot
     * live inside a table repeater's cell. The modal only writes to the hidden
     * `alternatives` field, so nothing is persisted until the form is saved.
     */
    private static function alternativesAction(): Action
    {
        return Action::make('alternatives')
            ->label(__('Alternatives'))
            ->icon(Heroicon::OutlinedSwatch)
            ->modalHeading(__('Alternatives'))
            ->modalSubmitActionLabel(__('Apply'))
            ->modalWidth(Width::FiveExtraLarge)
            ->badge(function (Repeater $component, array $arguments): ?string {
                $count = count(self::pendingAlternatives($component, $arguments));

                return $count > 0 ? (string) $count : null;
            })
            ->fillForm(fn (Repeater $component, array $arguments): array => [
                'alternatives' => self::pendingAlternatives($component, $arguments),
            ])
            ->schema([
                Repeater::make('alternatives')
                    ->hiddenLabel()
                    ->addActionLabel(__('Add alternative'))
                    ->reorderable()
                    ->table(self::ingredientTableColumns())
                    ->schema([
                        ...self::ingredientBaseFields(withAttributeRelationship: false),
                        Hidden::make('id'),
                    ])
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, Repeater $component, array $arguments): void {
                $itemKey = $arguments['item'] ?? null;

                if (! is_string($itemKey)) {
                    return;
                }

                self::alternativesField($component, $itemKey)
                    ?->state(array_values($data['alternatives'] ?? []));
            });
    }

    /**
     * The hidden field holding a row's pending alternatives.
     */
    private static function alternativesField(Repeater $component, string $itemKey): ?Hidden
    {
        $field = $component->getChildSchema($itemKey)
            ?->getComponent(fn (Component $child): bool => $child instanceof Hidden
                && $child->getName() === 'alternatives');

        return $field instanceof Hidden ? $field : null;
    }

    /**
     * The alternatives currently held in the row's form state, which may differ from
     * the database until the form is saved.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    private static function pendingAlternatives(Repeater $component, array $arguments): array
    {
        $itemKey = $arguments['item'] ?? null;

        if (! is_string($itemKey)) {
            return [];
        }

        // Read the field itself: getRawItemState() dehydrates, which drops this field.
        $state = self::alternativesField($component, $itemKey)?->getState();

        return is_array($state) ? array_values($state) : [];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $rows
     */
    private static function syncAlternatives(Ingredient $ingredient, array $rows): void
    {
        $keptIds = [];
        $position = 1;

        foreach ($rows as $row) {
            if (($row['food_id'] ?? null) === null) {
                continue;
            }

            $attributes = [
                'amount' => $row['amount'] ?? null,
                'amount_max' => $row['amount_max'] ?? null,
                'unit_id' => $row['unit_id'] ?? null,
                'food_id' => $row['food_id'],
                'position' => $position++,
            ];

            $id = $row['id'] ?? null;

            $alternative = $id
                ? $ingredient->ingredients()->whereKey($id)->first()
                : null;

            if ($alternative) {
                $alternative->update($attributes);
            } else {
                /** @var Ingredient $alternative */
                $alternative = $ingredient->ingredients()->create($attributes);
            }

            $alternative->ingredientAttributes()->sync($row['ingredientAttributes'] ?? []);

            $keptIds[] = $alternative->id;
        }

        $ingredient->ingredients()
            ->when($keptIds !== [], fn ($query) => $query->whereKeyNot($keptIds))
            ->each(fn (Ingredient $removed) => $removed->delete());
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
                    ->extraItemActions([self::alternativesAction(), self::moveIngredientAction()])
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
                                Repeater::make('topLevelIngredients')
                                    ->label(__('Ingredients'))
                                    ->relationship('topLevelIngredients')
                                    ->orderColumn('position')
                                    ->reorderable()
                                    ->extraItemActions([self::alternativesAction(), self::moveIngredientAction()])
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
