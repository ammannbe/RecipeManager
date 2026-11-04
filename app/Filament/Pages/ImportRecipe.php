<?php

namespace App\Filament\Pages;

use App\Ai\AiException;
use App\Ai\RecipeAssistant;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Mail\LookupRequestedMail;
use App\Models\Cookbook;
use App\Models\RecipeDraft;
use App\Models\User;
use App\Services\RecipeImport\LookupType;
use App\Services\RecipeImport\ParsedRecipe;
use App\Services\RecipeImport\RecipeImporter;
use App\Services\RecipeImport\RecipeJsonValidator;
use App\Services\RecipeImport\RelationResolver;
use App\Services\RecipeImport\UnresolvedValue;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $form
 */
class ImportRecipe extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Unresolved lookup values of the currently pasted JSON, serialised for Livewire.
     *
     * @var array<int, array{key: string, type: string, value: string, canCreate: bool, createsRequest: bool, canOmit: bool, suggestions: array<int, array{id: int, name: string, score: int}>, context: array<int, string>, preselectedId: int|null}>
     */
    public array $unresolved = [];

    /**
     * Summary of the parsed recipe, kept as state because the wizard renders the confirm
     * step in a later request than the one that parsed the JSON.
     *
     * @var array<string, string>
     */
    public array $summary = [];

    /**
     * Draft handed over by a companion app (?draft=<uuid>): the wizard starts at the resolve step.
     */
    public ?string $draftId = null;

    public int $startStep = 1;

    /**
     * Hash of the AI inputs the current JSON was read from.
     */
    public ?string $aiInput = null;

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
        return RecipeAssistant::allows(user())
            ? __('The AI reads a photo, PDF, text or web address of a recipe; you match unknown values and confirm.')
            : __('Paste a recipe in the JSON format, match unknown values and confirm.');
    }

    public function mount(): void
    {
        $this->form->fill();

        $draftId = request()->query('draft');

        if (is_string($draftId) && $draftId !== '') {
            $this->loadDraft($draftId);
        }
    }

    /**
     * Puts the draft JSON into the wizard and checks it, so the user lands on "Resolve".
     * Nothing is created before the user confirms the import.
     */
    private function loadDraft(string $id): void
    {
        $draft = RecipeDraft::query()->whereKey($id)->usableBy(user())->first();

        if ($draft === null) {
            Notification::make()
                ->warning()
                ->title(__('The recipe draft was not found or has expired.'))
                ->persistent()
                ->send();

            return;
        }

        $this->draftId = $draft->id;
        $this->data['json'] = (string) json_encode($draft->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $this->parse();
            $this->startStep = 2;
        } catch (ValidationException) {
            $this->startStep = 1;
        }
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
            $this->readStep(),
            $this->resolveStep(),
            $this->confirmStep(),
        ])
            ->startOnStep(fn (): int => $this->startStep)
            ->submitAction($this->importAction());
    }

    /**
     * Step 1: the recipe (photo, PDF, text or web address read by the AI, or recipe JSON, e.g. an
     * export) plus cookbook and visibility. "Next" reads it and goes straight to "Resolve".
     */
    private function readStep(): Step
    {
        $ai = RecipeAssistant::allows(user());

        return Step::make(__('Read recipe'))
            ->description($ai ? __('Photo, PDF, text or web address') : __('Recipe JSON'))
            ->icon(Heroicon::OutlinedSparkles)
            ->schema(fn (): array => [
                ...($ai ? [
                    FileUpload::make('ai_file')
                        ->label(__('Photo or PDF'))
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(10240)
                        ->storeFiles(false),
                    Textarea::make('ai_text')
                        ->label(__('Recipe text or web address'))
                        ->helperText(__('Paste the text of the recipe or the address of a recipe page (https://…). The AI reads it when you continue.'))
                        ->rows(5)
                        ->maxLength(20000),
                    Toggle::make('ai_image')
                        ->label(__('Also create a photo with AI'))
                        ->helperText(__('Added to the recipe after the import and marked as AI generated.')),
                ] : []),
                Section::make(__('Paste recipe JSON'))
                    ->description(__('For example from an export of this app.'))
                    ->collapsible($ai)
                    ->collapsed(fn (Get $get): bool => $ai && blank($get('json')))
                    ->schema([
                        Textarea::make('json')
                            ->hiddenLabel()
                            ->rows(12)
                            ->helperText(__('Paste the raw JSON object. Code fences are removed automatically.')),
                    ]),
                ...($this->draftId === null ? $this->publicationFields() : []),
            ])
            ->afterValidation(function (): void {
                $this->readRecipe();

                try {
                    $this->parse();
                } catch (ValidationException $exception) {
                    // The JSON section is collapsed when the AI wrote it: show the problem at the inputs.
                    if ($this->aiInput === null) {
                        throw $exception;
                    }

                    throw ValidationException::withMessages(['data.ai_text' => $exception->validator->errors()->all()]);
                }
            });
    }

    /**
     * Lets the AI read the inputs into the JSON when they changed since the last time (going back
     * and forth must not cost a request), or uses the pasted JSON.
     *
     * @throws ValidationException
     */
    private function readRecipe(): void
    {
        $file = collect((array) ($this->data['ai_file'] ?? []))->first(fn (mixed $file): bool => $file instanceof UploadedFile);
        $text = trim((string) ($this->data['ai_text'] ?? ''));

        if ($file === null && $text === '') {
            if (blank($this->data['json'] ?? null)) {
                throw ValidationException::withMessages(['data.ai_text' => __('Add a photo, PDF, text or web address of the recipe.')]);
            }

            return;
        }

        $hash = md5($text.'|'.($file !== null ? md5_file((string) $file->getRealPath()) : ''));

        if ($hash === $this->aiInput && filled($this->data['json'] ?? null)) {
            return;
        }

        set_time_limit(300);

        try {
            $recipe = app(RecipeAssistant::class)->recipe(user(), $file, $text);
        } catch (AiException $exception) {
            throw ValidationException::withMessages(['data.ai_text' => __($exception->getMessage())]);
        }

        $this->data['json'] = (string) json_encode($recipe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->aiInput = $hash;
    }

    /**
     * Cookbook and visibility: on the first step, or on "Resolve" for drafts, which skip it.
     *
     * @return array<int, Component>
     */
    private function publicationFields(): array
    {
        return [
            Select::make('cookbook_id')
                ->label(__('Cookbook'))
                ->nullable()
                ->searchable()
                ->preload()
                ->live()
                ->options(fn (): array => Cookbook::query()->administeredBy(user())->orderBy('name')->pluck('name', 'id')->all())
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $cookbook = $state ? Cookbook::query()->find($state) : null;
                    $set('is_public', $cookbook === null || $cookbook->is_public);
                }),
            Toggle::make('is_public')
                ->label(__('Public'))
                ->helperText(__('Public recipes and their images are visible to everyone, including visitors who are not logged in.'))
                ->live()
                ->default(true),
        ];
    }

    private function resolveStep(): Step
    {
        return Step::make(__('Resolve'))
            ->description(__('Match unknown values'))
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->schema(fn (): array => [
                ...($this->draftId !== null ? [
                    Section::make(__('Publication'))
                        ->schema($this->publicationFields())
                        ->columns(2),
                ] : []),
                ...$this->resolutionFields(),
            ])
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
            $decided = fn (callable $get): bool => $get($statePath.'.create') === true || $get($statePath.'.omit') === true;

            $select = Select::make($statePath.'.id')
                ->label($type->label())
                ->options($options)
                ->searchable()
                ->native(false)
                ->required(fn (callable $get): bool => ! $type->isOptional() && ! $decided($get))
                ->disabled($decided);

            if ($type->isOptional()) {
                $select->placeholder(__('Empty = omit'));
            }

            $components = [$select];

            if ($item['canCreate']) {
                $components[] = Toggle::make($statePath.'.create')
                    ->label($item['createsRequest']
                        ? __('Request ":value"', ['value' => $item['value']])
                        : __('Create ":value" instead', ['value' => $item['value']]))
                    ->helperText($item['createsRequest'] ? (string) __('Usable in your recipe right away; an administrator checks it before others see it.') : null)
                    ->live()
                    ->disabled(fn (callable $get): bool => $get($statePath.'.omit') === true)
                    ->default(false);
            }

            if ($item['canOmit']) {
                $components[] = Toggle::make($statePath.'.omit')
                    ->label(__('Omit'))
                    ->helperText($this->omitHint($type, $item))
                    ->live()
                    ->disabled(fn (callable $get): bool => $get($statePath.'.create') === true)
                    ->default(false);
            }

            $fields[] = Section::make($type->label().': '.$item['value'])
                ->description($this->resolutionDescription($item))
                ->schema($components)
                ->columns(2)
                // A unit or attribute only used on omitted ingredients needs no decision.
                ->hidden(fn (callable $get): bool => $this->isObsolete($item, fn (string $path): mixed => $get($path)));
        }

        return $fields;
    }

    /**
     * What omitting a value does to the recipe.
     *
     * @param  array{context: array<int, string>}  $item
     */
    private function omitHint(LookupType $type, array $item): string
    {
        return match ($type) {
            LookupType::Food => __('The ingredient is left out (with its alternatives). Check whether the instructions still mention it.'),
            LookupType::Unit => __('The amount stays, without a unit (e.g. "2 Salz" instead of "2 EL Salz").'),
            default => __('Only this entry is left out.'),
        };
    }

    /**
     * Whether a unit or attribute is only used on ingredients whose food is omitted.
     *
     * @param  array{type: string, context: array<int, string>}  $item
     * @param  callable(string): mixed  $get  form state by path (relative to "data")
     */
    private function isObsolete(array $item, callable $get): bool
    {
        if (! in_array($item['type'], [LookupType::Unit->value, LookupType::Attribute->value], true) || $item['context'] === []) {
            return false;
        }

        foreach ($item['context'] as $food) {
            $key = $this->stateKey(LookupType::Food->value.':'.mb_strtolower($food));

            if ($get('decisions.'.$key.'.omit') !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{value: string, canCreate: bool, createsRequest: bool, context: array<int, string>, preselectedId: int|null}  $item
     */
    private function resolutionDescription(array $item): string
    {
        $description = $item['preselectedId'] !== null
            ? __('A likely match is preselected. Please verify it.')
            : ($item['createsRequest']
                ? __('Choose an existing entry or request this one.')
                : ($item['canCreate']
                    ? __('Choose an existing entry or create this one.')
                    : __('Choose an existing entry. Only administrators may create new ones.')));

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

        foreach ([...$this->summary, ...$this->decisionSummary()] as $label => $value) {
            $lines[] = e($label).': '.e($value);
        }

        return new HtmlString('<ul class="list-disc ps-4"><li>'.implode('</li><li>', $lines).'</li></ul>');
    }

    /**
     * Omitted and requested values, so the user sees what the import will leave out.
     *
     * @return array<string, string>
     */
    private function decisionSummary(): array
    {
        $omitted = [];
        $requested = [];

        foreach ($this->unresolved as $item) {
            $type = LookupType::from($item['type']);
            $state = $this->data['decisions'][$this->stateKey($item['key'])] ?? [];
            $label = $type->label().' "'.$item['value'].'"';

            if ($this->isObsolete($item, fn (string $path): mixed => data_get($this->data, $path))) {
                continue;
            }

            if ((($state['omit'] ?? false) === true && $type->isOmittable())
                || ($type->isOptional() && ! is_numeric($state['id'] ?? null) && ($state['create'] ?? false) !== true)) {
                $omitted[] = $label.($item['context'] !== [] && $type !== LookupType::Food
                    ? ' ('.__('on: :ingredients', ['ingredients' => implode(', ', $item['context'])]).')'
                    : '');
            } elseif (($state['create'] ?? false) === true && $item['createsRequest']) {
                $requested[] = $label;
            }
        }

        return array_filter([
            __('Omitted') => implode('; ', $omitted),
            __('Requested') => implode('; ', $requested),
        ]);
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
                'createsRequest' => $value->canCreate && $value->type->createsRequest(user()),
                'canOmit' => $value->type->isOmittable(),
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
            $decision['omit'] ??= false;

            $this->data['decisions'][$key] = $decision;
        }
    }

    public function import(): void
    {
        $this->form->validate();

        $parsed = $this->parse();

        $resolver = app(RelationResolver::class);
        $decisions = [];
        $requested = [];

        foreach ($this->unresolved as $item) {
            $type = LookupType::from($item['type']);
            $state = $this->data['decisions'][$this->stateKey($item['key'])] ?? [];

            $omitted = (($state['omit'] ?? false) === true && $type->isOmittable())
                || $this->isObsolete($item, fn (string $path): mixed => data_get($this->data, $path));

            if ($omitted) {
                $decisions[$item['key']] = null;

                continue;
            }

            if (($state['create'] ?? false) === true) {
                if (! $type->canBeCreatedBy(user())) {
                    abort(403);
                }

                $decisions[$item['key']] = $resolver->create($type, $item['value'], user());

                if ($type->createsRequest(user())) {
                    $requested[] = ['type' => $type->value, 'value' => $item['value']];
                }

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

        $cookbookId = $this->data['cookbook_id'] ?? null;

        try {
            $recipe = app(RecipeImporter::class)->import(
                $parsed,
                user(),
                $decisions,
                cookbookId: $cookbookId !== null ? (int) $cookbookId : null,
                isPublic: (bool) ($this->data['is_public'] ?? true),
            );
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

        if ($this->draftId !== null) {
            RecipeDraft::query()->whereKey($this->draftId)->delete();
        }

        LookupRequestedMail::notifyAdmins(user(), $requested, $recipe->name);

        if (($this->data['ai_image'] ?? false) === true && RecipeAssistant::allows(user())) {
            set_time_limit(300);

            try {
                app(RecipeAssistant::class)->image(user(), $recipe);
            } catch (AiException $exception) {
                // The recipe is imported; only the photo is missing.
                Notification::make()->warning()->title(__('The AI photo could not be created.'))->body(__($exception->getMessage()))->persistent()->send();
            }
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
