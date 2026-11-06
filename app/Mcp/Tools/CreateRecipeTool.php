<?php

namespace App\Mcp\Tools;

use App\Enums\Measure;
use App\Mail\LookupRequestedMail;
use App\Mcp\McpPermission;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Models\User;
use App\Services\FoodConversions;
use App\Services\RecipeImport\LookupType;
use App\Services\RecipeImport\RecipeImporter;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipeImport\RecipeJsonValidator;
use App\Services\RecipeImport\RelationResolver;
use App\Services\RecipeImport\UnresolvedValue;
use App\Services\SwissSpelling;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create-recipe')]
#[Description('Creates a recipe from the import JSON format (see get-recipe-format; no photos, add them afterwards with add-recipe-photo or generate-recipe-photo). Known categories, units, foods, attributes and tags are matched by name. Check unknown names first with find-similar. Nothing is created without the user: unknown values are only created when listed in create (for non-admins as requests an admin approves later) or mapped to an existing entry with map; otherwise nothing is saved and the answer lists them with similar existing entries (suggestions) and a link to the import wizard. Then ask the user which existing entry to use or whether to create the value. A name already used in the cookbook (also by a recipe in the trash) gets a number like "Name (2)" unless on_duplicate is "error".')]
class CreateRecipeTool extends RecipeTool
{
    public function __construct(
        private RecipeJsonValidator $validator,
        private RelationResolver $resolver,
        private RecipeImporter $importer,
        private FoodConversions $foodConversions,
    ) {}

    protected function permission(): McpPermission
    {
        return McpPermission::Create;
    }

    public function handle(Request $request): Response
    {
        $user = $this->user($request);
        $data = $request->validate([
            'recipe' => ['required', 'array'],
            'recipe.photos' => ['prohibited'],
            'cookbook_id' => ['nullable', 'integer'],
            'is_public' => ['nullable', 'boolean'],
            'create_missing' => ['nullable', 'boolean'],
            'create' => ['nullable', 'array', 'max:100'],
            'create.*' => ['string', 'max:100'],
            'map' => ['nullable', 'array', 'max:100'],
            'map.*' => ['string', 'max:100'],
            'conversions' => ['nullable', 'array', 'max:100'],
            'conversions.*' => ['array'],
            'on_duplicate' => ['nullable', 'in:rename,error'],
        ]);

        // validated() keeps only the ruled keys of a nested array (and reads dots in keys as
        // nesting), so take these as sent.
        $data['recipe'] = (array) $request->get('recipe');
        $data['map'] = (array) $request->get('map', []);
        $data['conversions'] = (array) $request->get('conversions', []);
        // Swiss spelling (RECIPE_SPELLING) also for the names create, map and conversions refer to.
        $data['recipe'] = SwissSpelling::recipe($data['recipe']);

        if (! $user->can('create', Recipe::class)) {
            return Response::error('You may not create recipes.');
        }

        $cookbookId = isset($data['cookbook_id']) ? (int) $data['cookbook_id'] : null;

        if ($cookbookId !== null && ! Cookbook::query()->administeredBy($user)->whereKey($cookbookId)->exists()) {
            return Response::error('You may not add recipes to this cookbook. See list-vocabulary for the cookbooks with can_add_recipes.');
        }

        try {
            $parsed = $this->validator->validate((string) json_encode($data['recipe']));
        } catch (ValidationException $exception) {
            return Response::error('The recipe is invalid: '.implode(' ', $exception->validator->errors()->all()));
        }

        try {
            $conversions = $this->conversions($data['conversions'], $user);
        } catch (ValidationException $exception) {
            return Response::error('Invalid conversions: '.implode(' ', $exception->validator->errors()->all()));
        }

        $create = array_map(fn (string $name): string => mb_strtolower(trim(SwissSpelling::text($name))), (array) ($data['create'] ?? []));
        $map = [];

        foreach ($data['map'] as $from => $to) {
            $map[mb_strtolower(trim(SwissSpelling::text((string) $from)))] = trim((string) $to);
        }

        $decisions = [];
        $missing = [];
        $open = [];
        $matched = [];
        $requested = [];

        foreach ($this->resolver->resolve($parsed, $user)->unresolved as $value) {
            $canCreate = $value->canCreate && $value->type->canBeCreatedBy($user);
            $target = $map[mb_strtolower($value->value)] ?? null;
            $problem = null;

            if ($target !== null) {
                $existing = $this->resolver->existing($value->type, $target, $user);

                if ($existing !== null) {
                    $decisions[$value->key()] = (int) $existing->getKey();

                    continue;
                }

                $problem = 'No existing '.$value->type->value.' named "'.$target.'" for map.';
            } elseif (in_array(mb_strtolower($value->value), $create, true) && $canCreate) {
                $missing[] = $value;

                continue;
            } elseif ($value->preselectedId() !== null) {
                $decisions[$value->key()] = $value->preselectedId();
                $matched[] = ['type' => $value->type->value, 'value' => $value->value, 'matched' => $value->suggestions[0]['name']];

                continue;
            } elseif (($data['create_missing'] ?? false) && $canCreate && $value->suggestions === []) {
                // Only without similar entries: "Rüebli" must not become a second "Karotte".
                $missing[] = $value;

                continue;
            }

            // Also optional ones (attributes, tags): the AI should not lose them silently.
            $open[] = [
                'type' => $value->type->value,
                'value' => $value->value,
                'suggestions' => array_column($value->suggestions, 'name'),
                'can_create' => $canCreate,
                ...($problem !== null ? ['problem' => $problem] : []),
            ];
        }

        if ($open !== []) {
            $draft = RecipeDraft::query()->create([
                'user_id' => $user->id,
                'data' => $data['recipe'],
                'expires_at' => now()->addHours(RecipeDraft::LIFETIME_HOURS),
            ]);

            return Response::json([
                'saved' => false,
                'message' => 'Nothing was saved: some values are unknown. Ask the user what to do with each of them, do not decide yourself when there are suggestions: '
                    .'use an existing entry (call again with map: {"value": "existing name"}), create it (call again with create: ["value"], only with can_create), '
                    .'or let the user match everything in the import wizard (import_wizard_url).',
                'unknown' => $open,
                'import_wizard_url' => route('filament.app.pages.import-recipe', ['draft' => $draft->id]),
            ]);
        }

        // Conversions only go to foods created here; existing foods are maintained by admins.
        $newFoods = array_map(
            fn (UnresolvedValue $value): string => mb_strtolower($value->value),
            array_filter($missing, fn (UnresolvedValue $value): bool => $value->type === LookupType::Food),
        );
        $ignored = array_column(array_diff_key($conversions, array_flip($newFoods)), 'name');

        try {
            // Missing values and the recipe are saved together: a failed import leaves no requests behind.
            $recipe = DB::transaction(function () use ($parsed, $user, $decisions, $missing, $conversions, $cookbookId, $data, &$requested, &$ignored): Recipe {
                $handled = [];

                foreach ($missing as $value) {
                    $id = $this->resolver->create($value->type, $value->value, $user);
                    $decisions[$value->key()] = $id;
                    $conversion = $value->type === LookupType::Food ? ($conversions[mb_strtolower($value->value)] ?? null) : null;

                    // An existing entry (also in the trash or another user's request) was reused, or an
                    // earlier value of this call created it already: its data stays as it is.
                    if (! $this->resolver->wasCreated($value->type, $id) || isset($handled[$value->type->value][$id])) {
                        if ($conversion !== null) {
                            $ignored[] = $conversion['name'];
                        }

                        continue;
                    }

                    $handled[$value->type->value][$id] = true;

                    if ($conversion !== null) {
                        $this->foodConversions->apply(Food::query()->findOrFail($id), $conversion['conversion']);
                    }

                    if ($value->type->createsRequest($user)) {
                        $requested[] = ['type' => $value->type->value, 'value' => $value->value];
                    }
                }

                return $this->importer->import(
                    $parsed,
                    $user,
                    $decisions,
                    $cookbookId,
                    isset($data['is_public']) ? (bool) $data['is_public'] : null,
                    renameDuplicate: ($data['on_duplicate'] ?? 'rename') === 'rename',
                );
            });
        } catch (ValidationException $exception) {
            return Response::error('The recipe could not be saved: '.implode(' ', $exception->validator->errors()->all()));
        }

        LookupRequestedMail::notifyAdmins($user, $requested, $recipe->name);

        $renamed = $recipe->name !== $parsed->name;

        return Response::json([
            'saved' => true,
            'id' => $recipe->id,
            'name' => $recipe->name,
            'renamed' => $renamed,
            ...($renamed ? ['original_name' => $parsed->name] : []),
            'is_public' => $recipe->is_public,
            'requested' => $requested,
            ...($matched !== [] ? ['matched_similar' => $matched] : []),
            ...($ignored !== [] ? ['conversions_ignored' => $ignored] : []),
            'url' => route('recipes.show', $recipe),
        ]);
    }

    /**
     * Conversions by lowercased food name, checked like in the import wizard.
     *
     * @param  array<mixed>  $input  food name => {measure, density, piece_weights: {unit name: grams}, season_months, storage_months}
     * @return array<string, array{name: string, conversion: array{measure: Measure|null, density: float|null, weights: array<int, float>, season_months: list<int>, storage_months: list<int>}}>
     *
     * @throws ValidationException
     */
    private function conversions(array $input, User $user): array
    {
        $conversions = [];

        foreach ($input as $food => $values) {
            $food = trim(SwissSpelling::text((string) $food));
            $values = is_array($values) ? $values : [];
            $weights = [];

            foreach ((array) ($values['piece_weights'] ?? []) as $unit => $grams) {
                $unitId = $this->foodConversions->pieceUnitId((string) $unit, $user);

                if ($unitId === null) {
                    throw ValidationException::withMessages(['conversions' => '"'.$unit.'" ('.$food.') is no piece unit. Piece units: '.implode(', ', $this->foodConversions->pieceUnits($user)).'.']);
                }

                $weights[] = ['unit_id' => $unitId, 'grams' => $grams];
            }

            $conversions[mb_strtolower($food)] = [
                'name' => $food,
                'conversion' => $this->foodConversions->validate([
                    'measure' => $values['measure'] ?? null,
                    'density' => $values['density'] ?? null,
                    'weights' => $weights,
                    'season_months' => $values['season_months'] ?? [],
                    'storage_months' => $values['storage_months'] ?? [],
                ], $user, $food),
            ];
        }

        return $conversions;
    }

    public function description(): string
    {
        return trim(parent::description().' '.SwissSpelling::instruction());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'recipe' => $schema->object()->description('The recipe in the import JSON format: '.preg_replace('/\s+/', ' ', RecipeJsonSchema::describe()))->required(),
            'cookbook_id' => $schema->integer()->description('Cookbook to add it to (list-vocabulary, can_add_recipes). Without: no cookbook.'),
            'is_public' => $schema->boolean()->description('Visible to everyone. Default: public, unless the cookbook is private.'),
            'create' => $schema->array()->items($schema->string())->description('Unknown values (as written in the recipe) the user agreed to create, e.g. ["Yuzu"]. For non-admins foods, units, attributes and tags become requests an admin approves.'),
            'map' => $schema->object()->description('Unknown values mapped to existing entries of the same kind by name, e.g. {"Rüebli": "Karotte"}. Use the suggestions or find-similar, as the user decided.'),
            'create_missing' => $schema->boolean()->description('Deprecated, prefer create. Creates unknown values that have no similar existing entry; values with suggestions are still returned as unknown.'),
            'conversions' => $schema->object()->description('Optional conversion and season data for foods this call creates: {"Food name": {"measure": "mass"|"volume"|null, "density": grams per ml ('.FoodConversions::MIN_DENSITY.'-'.FoodConversions::MAX_DENSITY.') or null, "piece_weights": {"Stück": 60}, "season_months": [5, 6, 7], "storage_months": [1, 2]}}. piece_weights only for existing piece units (units without measure such as Stück, Zehe, Bund), grams > 0. season_months: months (1-12, each once) of fresh Swiss harvest (Swiss seasonal calendar, open field or unheated greenhouse); storage_months: months only available from Swiss storage; leave both out or empty for foods without a Swiss season (spices, oil, pasta …). Every field is optional. Ignored for existing foods.'),
            'on_duplicate' => $schema->string()->enum(['rename', 'error'])->description('When the cookbook (without cookbook: the user\'s recipes without cookbook) already has a recipe with this name, also in the trash. "rename" (default): save it as "Name (2)" … and answer with renamed and original_name. "error": save nothing and name the conflict.'),
        ];
    }
}
