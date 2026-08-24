<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Services\RecipeImport\ImportPromptBuilder;
use App\Services\RecipeImport\LookupType;
use App\Services\RecipeImport\ParsedRecipe;
use App\Services\RecipeImport\RecipeImporter;
use App\Services\RecipeImport\RecipeJsonValidator;
use App\Services\RecipeImport\RelationResolver;
use App\Services\RecipeImport\UnresolvedValue;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $form
 */
class ImportRecipe extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = true;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Unresolved lookup values of the currently pasted JSON, serialised for Livewire.
     *
     * @var array<int, array{key: string, type: string, value: string, canCreate: bool, suggestions: array<int, array{id: int, name: string, score: int}>, context: array<int, string>, preselectedId: int|null}>
     */
    public array $unresolved = [];

    /**
     * Summary of the parsed recipe, kept as state because the wizard renders the confirm
     * step in a later request than the one that parsed the JSON.
     *
     * @var array<string, string>
     */
    public array $summary = [];

    private ?ParsedRecipe $parsed = null;

    public static function getNavigationLabel(): string
    {
        return __('Import recipe');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Import recipe');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Convert a photo, PDF or text into a recipe using an AI, then import the JSON here.');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([$this->wizard()])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form'),
        ]);
    }

    private function wizard(): Component
    {
        return Wizard::make([
            $this->promptStep(),
            $this->jsonStep(),
            $this->resolveStep(),
            $this->confirmStep(),
        ])
            ->submitAction($this->importAction());
    }

    private function promptStep(): Step
    {
        return Step::make(__('AI prompt'))
            ->description(__('Copy this into your AI together with the recipe'))
            ->icon(Heroicon::OutlinedSparkles)
            ->schema([
                Text::make(__('Paste the prompt below into an AI chat and attach the recipe photo, PDF or text. The AI answers with JSON that you paste into the next step.')),
                Textarea::make('prompt')
                    ->hiddenLabel()
                    ->readOnly()
                    ->rows(16)
                    ->default(fn (ImportPromptBuilder $builder): string => $builder->build(user()))
                    ->belowContent($this->copyPromptAction()),
            ]);
    }

    private function jsonStep(): Step
    {
        return Step::make(__('Recipe JSON'))
            ->description(__('Paste the AI answer'))
            ->icon(Heroicon::OutlinedCodeBracket)
            ->schema([
                Textarea::make('json')
                    ->label(__('Recipe JSON'))
                    ->required()
                    ->rows(16)
                    ->helperText(__('Paste the raw JSON object. Code fences are removed automatically.')),
            ])
            ->afterValidation(function (): void {
                $this->parse();
            });
    }

    private function resolveStep(): Step
    {
        return Step::make(__('Resolve'))
            ->description(__('Match unknown values'))
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->schema(fn (): array => $this->resolutionFields())
            ->afterValidation(function (): void {
                $this->parse();
            });
    }

    private function confirmStep(): Step
    {
        return Step::make(__('Confirm'))
            ->description(__('Review and import'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->schema([
                Placeholder::make('summaryPlaceholder')
                    ->hiddenLabel()
                    ->content(fn (): Htmlable => $this->summaryHtml()),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    private function resolutionFields(): array
    {
        if ($this->unresolved === []) {
            return [Text::make(__('Everything could be matched automatically. Nothing to do here.'))];
        }

        $resolver = app(RelationResolver::class);
        $fields = [];

        foreach ($this->unresolved as $item) {
            $type = LookupType::from($item['type']);
            $options = [];

            foreach ($item['suggestions'] as $suggestion) {
                $options[$suggestion['id']] = $suggestion['name'].' — '.__(':score% match', [
                    'score' => $suggestion['score'],
                ]);
            }

            foreach ($resolver->options($type, user()) as $id => $name) {
                $options[$id] ??= $name;
            }

            $statePath = 'decisions.'.$this->stateKey($item['key']);

            $select = Select::make($statePath.'.id')
                ->label($type->label())
                ->options($options)
                ->searchable()
                ->native(false)
                ->required(fn (callable $get): bool => ! $type->isOptional() && $get($statePath.'.create') !== true)
                ->disabled(fn (callable $get): bool => $get($statePath.'.create') === true);

            if ($type->isOptional()) {
                $select->placeholder(__('Omit'));
            }

            $components = [$select];

            if ($item['canCreate']) {
                $components[] = Toggle::make($statePath.'.create')
                    ->label(__('Create ":value" instead', ['value' => $item['value']]))
                    ->live()
                    ->default(false);
            }

            $fields[] = Section::make($type->label().': '.$item['value'])
                ->description($this->resolutionDescription($item))
                ->schema($components)
                ->columns(2);
        }

        return $fields;
    }

    /**
     * @param  array{value: string, canCreate: bool, context: array<int, string>, preselectedId: int|null}  $item
     */
    private function resolutionDescription(array $item): string
    {
        $description = $item['preselectedId'] !== null
            ? __('A likely match is preselected. Please verify it.')
            : ($item['canCreate']
                ? __('Choose an existing entry or create this one.')
                : __('Choose an existing entry. Only administrators may create new ones.'));

        if ($item['context'] !== []) {
            $description = __('Used on: :ingredients', [
                'ingredients' => implode(', ', $item['context']),
            ]).' — '.$description;
        }

        return $description;
    }

    private function summaryHtml(): Htmlable
    {
        if ($this->summary === []) {
            return new HtmlString('');
        }

        $lines = [];

        foreach ($this->summary as $label => $value) {
            $lines[] = e($label).': '.e($value);
        }

        return new HtmlString('<ul class="list-disc ps-4"><li>'.implode('</li><li>', $lines).'</li></ul>');
    }

    /**
     * @return array<string, string>
     */
    private function buildSummary(ParsedRecipe $parsed): array
    {
        return [
            __('Recipe') => $parsed->name,
            __('Category') => $parsed->category,
            __('Ingredients') => (string) count($parsed->allIngredients()),
            __('Ingredient groups') => (string) count($parsed->groups),
            __('Tags') => $parsed->tags === [] ? '—' : implode(', ', $parsed->tags),
        ];
    }

    private function copyPromptAction(): Action
    {
        return Action::make('copyPrompt')
            ->label(__('Copy prompt'))
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->action(function (): void {
                $prompt = app(ImportPromptBuilder::class)->build(user());

                $this->js('window.navigator.clipboard.writeText('.json_encode($prompt).')');

                Notification::make()->success()->title(__('Prompt copied'))->send();
            });
    }

    public function importAction(): Action
    {
        return Action::make('import')
            ->label(__('Import recipe'))
            ->action(function (): void {
                $this->import();
            });
    }

    /**
     * Decodes and validates the pasted JSON, refreshing the list of unresolved values.
     */
    private function parse(): ParsedRecipe
    {
        $json = trim((string) ($this->data['json'] ?? ''));

        // AI models routinely wrap the answer in a markdown code fence.
        $json = (string) preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $json);

        try {
            $this->parsed = app(RecipeJsonValidator::class)->validate($json);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'data.json' => $exception->validator->errors()->all(),
            ]);
        }

        $report = app(RelationResolver::class)->resolve($this->parsed, user());

        $this->unresolved = array_map(
            fn (UnresolvedValue $value): array => [
                'key' => $value->key(),
                'type' => $value->type->value,
                'value' => $value->value,
                'canCreate' => $value->canCreate,
                'suggestions' => $value->suggestions,
                'context' => $value->context,
                'preselectedId' => $value->preselectedId(),
            ],
            $report->unresolved
        );

        $this->preselectDecisions();

        $this->summary = $this->buildSummary($this->parsed);

        return $this->parsed;
    }

    /**
     * Every unresolved value needs a decision entry before the review step renders,
     * otherwise its fields have nothing to bind to. Confident suggestions are filled in,
     * without overwriting anything the user already picked.
     */
    private function preselectDecisions(): void
    {
        foreach ($this->unresolved as $item) {
            $key = $this->stateKey($item['key']);
            $decision = $this->data['decisions'][$key] ?? [];

            $decision['id'] ??= $item['preselectedId'];
            $decision['create'] ??= false;

            $this->data['decisions'][$key] = $decision;
        }
    }

    public function import(): void
    {
        $this->form->validate();

        $parsed = $this->parse();

        $resolver = app(RelationResolver::class);
        $decisions = [];

        foreach ($this->unresolved as $item) {
            $type = LookupType::from($item['type']);
            $state = $this->data['decisions'][$this->stateKey($item['key'])] ?? [];

            if (($state['create'] ?? false) === true) {
                if (! $type->canBeCreatedBy(user())) {
                    abort(403);
                }

                $decisions[$item['key']] = $resolver->create($type, $item['value'], user());

                continue;
            }

            $id = $state['id'] ?? null;

            if (! is_numeric($id)) {
                if ($type->isOptional()) {
                    $decisions[$item['key']] = null;

                    continue;
                }

                throw ValidationException::withMessages([
                    'data.decisions' => __('Please resolve :type ":value".', [
                        'type' => $type->label(),
                        'value' => $item['value'],
                    ]),
                ]);
            }

            $decisions[$item['key']] = (int) $id;
        }

        try {
            $recipe = app(RecipeImporter::class)->import($parsed, user(), $decisions);
        } catch (ValidationException $exception) {
            $messages = $exception->validator->errors()->all();

            Notification::make()
                ->danger()
                ->title(__('Recipe could not be imported'))
                ->body(implode(' ', $messages))
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'data.json' => $messages,
            ]);
        }

        Notification::make()
            ->success()
            ->title(__('Recipe imported'))
            ->body($recipe->name)
            ->send();

        $this->redirect(RecipeResource::getUrl('edit', ['record' => $recipe]));
    }

    /**
     * Lookup keys contain dots and colons, which Livewire would read as nesting.
     */
    private function stateKey(string $key): string
    {
        return md5($key);
    }
}
