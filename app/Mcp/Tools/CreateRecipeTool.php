<?php

namespace App\Mcp\Tools;

use App\Mail\LookupRequestedMail;
use App\Mcp\McpPermission;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\RecipeDraft;
use App\Services\RecipeImport\RecipeImporter;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipeImport\RecipeJsonValidator;
use App\Services\RecipeImport\RelationResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create-recipe')]
#[Description('Creates a recipe from the import JSON format (see get-recipe-format; no photos). Known categories, units, foods, attributes and tags are matched by name. Unknown values: with create_missing they are created (for non-admins as requests an admin approves later), otherwise nothing is saved and the answer lists them together with a link to the import wizard, where the user matches them. A name already used in the cookbook (also by a recipe in the trash) gets a number like "Name (2)" unless on_duplicate is "error".')]
class CreateRecipeTool extends RecipeTool
{
    public function __construct(
        private RecipeJsonValidator $validator,
        private RelationResolver $resolver,
        private RecipeImporter $importer,
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
            'on_duplicate' => ['nullable', 'in:rename,error'],
        ]);

        // validated() keeps only the ruled keys of a nested array, so take the recipe as sent.
        $data['recipe'] = (array) $request->get('recipe');

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

        $decisions = [];
        $missing = [];
        $open = [];
        $requested = [];

        foreach ($this->resolver->resolve($parsed, $user)->unresolved as $value) {
            if ($value->preselectedId() !== null) {
                $decisions[$value->key()] = $value->preselectedId();
            } elseif (($data['create_missing'] ?? false) && $value->canCreate && $value->type->canBeCreatedBy($user)) {
                $missing[] = $value;
            } else {
                // Also optional ones (attributes, tags): the AI should not lose them silently.
                $open[] = [
                    'type' => $value->type->value,
                    'value' => $value->value,
                    'suggestions' => array_column($value->suggestions, 'name'),
                    'can_create' => $value->canCreate && $value->type->canBeCreatedBy($user),
                ];
            }
        }

        if ($open !== []) {
            $draft = RecipeDraft::query()->create([
                'user_id' => $user->id,
                'data' => $data['recipe'],
                'expires_at' => now()->addHours(RecipeDraft::LIFETIME_HOURS),
            ]);

            return Response::json([
                'saved' => false,
                'message' => 'Unknown values. Use a suggested name, call again with create_missing, or let the user match them in the import wizard.',
                'unknown' => $open,
                'import_wizard_url' => route('filament.app.pages.import-recipe', ['draft' => $draft->id]),
            ]);
        }

        try {
            // Missing values and the recipe are saved together: a failed import leaves no requests behind.
            $recipe = DB::transaction(function () use ($parsed, $user, $decisions, $missing, $cookbookId, $data, &$requested): Recipe {
                foreach ($missing as $value) {
                    $decisions[$value->key()] = $this->resolver->create($value->type, $value->value, $user);

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
            'url' => route('recipes.show', $recipe),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'recipe' => $schema->object()->description('The recipe in the import JSON format: '.preg_replace('/\s+/', ' ', RecipeJsonSchema::describe()))->required(),
            'cookbook_id' => $schema->integer()->description('Cookbook to add it to (list-vocabulary, can_add_recipes). Without: no cookbook.'),
            'is_public' => $schema->boolean()->description('Visible to everyone. Default: public, unless the cookbook is private.'),
            'create_missing' => $schema->boolean()->description('Create unknown categories, units, foods, attributes and tags instead of stopping. Only when the user agrees.'),
            'on_duplicate' => $schema->string()->enum(['rename', 'error'])->description('When the cookbook (without cookbook: the user\'s recipes without cookbook) already has a recipe with this name, also in the trash. "rename" (default): save it as "Name (2)" … and answer with renamed and original_name. "error": save nothing and name the conflict.'),
        ];
    }
}
