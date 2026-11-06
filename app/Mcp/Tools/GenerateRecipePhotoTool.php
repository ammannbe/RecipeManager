<?php

namespace App\Mcp\Tools;

use App\Ai\AiException;
use App\Ai\RecipeAssistant;
use App\Mcp\McpPermission;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('generate-recipe-photo')]
#[Description('Lets the app\'s AI create a photo of the dish from the recipe name and ingredients and adds it to a recipe the user may edit, marked as AI generated. Counts against the user\'s hourly AI limit; takes up to a minute or two.')]
class GenerateRecipePhotoTool extends RecipeTool
{
    public function __construct(private RecipeAssistant $assistant) {}

    protected function permission(): McpPermission
    {
        return McpPermission::Update;
    }

    /**
     * Only listed when the user may use the AI features.
     */
    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return parent::shouldRegister($request) && $user instanceof User && RecipeAssistant::allows($user);
    }

    public function handle(Request $request): Response
    {
        $user = $this->user($request);
        $data = $request->validate(['recipe_id' => ['required', 'integer']]);

        /** @var Recipe|null $recipe */
        $recipe = Recipe::query()->find((int) $data['recipe_id']);

        if ($recipe === null || ! $user->can('update', $recipe)) {
            return Response::error('No recipe with this id that you may edit.');
        }

        if (! RecipeAssistant::allows($user)) {
            return Response::error('The AI features are not available for this user.');
        }

        set_time_limit(300);

        try {
            $photo = $this->assistant->image($user, $recipe);
        } catch (AiException $exception) {
            return Response::error('The photo was not created: '.$exception->getMessage());
        }

        return Response::json([
            'saved' => true,
            'recipe_id' => $recipe->id,
            'photo' => [...$photo, 'url' => route('recipes.photo', ['recipe' => $recipe->id, 'filename' => $photo['path']])],
            'photos' => $recipe->photos->count(),
            'edit_url' => route('filament.app.resources.recipes.edit', ['record' => $recipe]),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'recipe_id' => $schema->integer()->description('The recipe id.')->required(),
        ];
    }
}
