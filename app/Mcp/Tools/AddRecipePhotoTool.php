<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Models\Recipe;
use App\Services\PublicUrlException;
use App\Services\RecipeImport\ImageDownloader;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipePhotos;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add-recipe-photo')]
#[Description('Adds a photo to a recipe the user may edit, after the existing photos: either url (a public, directly downloadable JPEG, PNG, WebP or GIF, max. 10 MB) or data (a base64 data URI like "data:image/jpeg;base64,...", max. 5 MB). Optionally source (e.g. website or photographer) and is_ai_generated.')]
class AddRecipePhotoTool extends RecipeTool
{
    public function __construct(
        private ImageDownloader $downloader,
        private RecipePhotos $photos,
    ) {}

    protected function permission(): McpPermission
    {
        return McpPermission::Update;
    }

    public function handle(Request $request): Response
    {
        $user = $this->user($request);
        $data = $request->validate([
            'recipe_id' => ['required', 'integer'],
            'url' => ['nullable', 'required_without:data', 'prohibits:data', 'string', 'max:2048'],
            // Base64 needs 4 characters per 3 bytes, plus the "data:…;base64," prefix.
            'data' => ['nullable', 'required_without:url', 'string', 'max:'.(int) ceil(RecipeJsonSchema::MAX_PHOTO_BYTES / 3 * 4 + 100)],
            'source' => ['nullable', 'string', 'max:255'],
            'is_ai_generated' => ['nullable', 'boolean'],
        ], [
            'data.max' => 'The photo is too large (max. 5 MB).',
        ]);

        /** @var Recipe|null $recipe */
        $recipe = Recipe::query()->find((int) $data['recipe_id']);

        if ($recipe === null || ! $user->can('update', $recipe)) {
            return Response::error('No recipe with this id that you may edit.');
        }

        try {
            [$binary, $extension] = filled($data['url'] ?? null)
                ? $this->downloader->fetch((string) $data['url'], RecipePhotos::MAX_DOWNLOAD_BYTES)
                : $this->photos->decode((string) $data['data']);
        } catch (PublicUrlException|InvalidArgumentException $exception) {
            return Response::error('The photo was not added: '.$exception->getMessage());
        }

        $photo = $this->photos->add($recipe, $binary, $extension, $data['source'] ?? null, (bool) ($data['is_ai_generated'] ?? false));

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
            'url' => $schema->string()->description('Public image URL (http/https). Either url or data.'),
            'data' => $schema->string()->description('Base64 data URI of the image. Either url or data.'),
            'source' => $schema->string()->description('Where the photo comes from, e.g. the website or photographer.'),
            'is_ai_generated' => $schema->boolean()->description('Whether an AI created the photo. Default: false.'),
        ];
    }
}
