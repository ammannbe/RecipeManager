<?php

namespace App\Services;

use App\Models\Recipe;
use App\Services\RecipeImport\RecipeJsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Checks and stores recipe photos (private disk "recipes", directory {recipe id}/): the same
 * checks for the JSON import, MCP and AI photos.
 */
class RecipePhotos
{
    /**
     * Downloads (MCP add-recipe-photo) may be larger than base64 photos: like the wizard's upload
     * limit. Base64 photos travel inside the JSON-RPC or import payload and stay at
     * RecipeJsonSchema::MAX_PHOTO_BYTES.
     */
    public const MAX_DOWNLOAD_BYTES = 10 * 1024 * 1024;

    public const FULL = 'The recipe already has the maximum of '.RecipeJsonSchema::MAX_PHOTOS.' photos.';

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * The file extension of an allowed image, judged by its content.
     *
     * @throws InvalidArgumentException
     */
    public function extension(string $binary, int $maxBytes = RecipeJsonSchema::MAX_PHOTO_BYTES): string
    {
        if (strlen($binary) > $maxBytes) {
            throw new InvalidArgumentException('The file is too large.');
        }

        $mime = $binary !== '' ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) : false;

        if (! is_string($mime) || ! in_array($mime, RecipeJsonSchema::ALLOWED_PHOTO_MIMES, true)) {
            throw new InvalidArgumentException('The file is not a supported image (JPEG, PNG, WebP or GIF).');
        }

        return self::EXTENSIONS[$mime];
    }

    /**
     * Decodes a base64 data URI, rejecting anything that is not an allowed image.
     *
     * @return array{0: string, 1: string} binary contents and file extension
     *
     * @throws InvalidArgumentException
     */
    public function decode(string $data, int $maxBytes = RecipeJsonSchema::MAX_PHOTO_BYTES): array
    {
        if (! preg_match('#^data:([a-z/+.-]+);base64,#i', $data, $matches)) {
            throw new InvalidArgumentException('The photo must be a base64 data URI like "data:image/jpeg;base64,...".');
        }

        if (! in_array(strtolower($matches[1]), RecipeJsonSchema::ALLOWED_PHOTO_MIMES, true)) {
            throw new InvalidArgumentException('The file is not a supported image (JPEG, PNG, WebP or GIF).');
        }

        $binary = base64_decode(substr($data, strlen($matches[0])), true);

        if ($binary === false) {
            throw new InvalidArgumentException('The photo is not valid base64.');
        }

        return [$binary, $this->extension($binary, $maxBytes)];
    }

    /**
     * Whether the recipe has room for another photo (the JSON import's limit).
     */
    public function hasRoom(Recipe $recipe): bool
    {
        return $recipe->photos->count() < RecipeJsonSchema::MAX_PHOTOS;
    }

    /**
     * Stores the image and appends it to the recipe's photos. The photos are re-read under a row
     * lock, so photos added at the same time are not lost; the file is removed if saving fails.
     *
     * @return array{path: string, source: string|null, is_ai_generated: bool}
     *
     * @throws InvalidArgumentException when the recipe already has the maximum of photos
     */
    public function add(Recipe $recipe, string $binary, string $extension, ?string $source, bool $isAiGenerated, string $suffix = ''): array
    {
        $filename = (Str::slug($recipe->name) ?: 'photo').($suffix !== '' ? '-'.$suffix : '').'-'.Str::lower(Str::random(6)).'.'.$extension;
        $path = $recipe->getKey().'/'.$filename;
        $photo = ['path' => $filename, 'source' => $source, 'is_ai_generated' => $isAiGenerated];

        Storage::disk('recipes')->put($path, $binary);

        try {
            $fresh = DB::transaction(function () use ($recipe, $photo): Recipe {
                /** @var Recipe $fresh */
                $fresh = Recipe::withTrashed()->lockForUpdate()->findOrFail($recipe->getKey());

                if (! $this->hasRoom($fresh)) {
                    throw new InvalidArgumentException(self::FULL);
                }

                $photos = array_map(fn (Document $document): array => $document->jsonSerialize(), array_values($fresh->photos->all()));
                $photos[] = $photo;

                $fresh->photos = $photos;
                $fresh->save();

                return $fresh;
            });
        } catch (Throwable $exception) {
            Storage::disk('recipes')->delete($path);

            throw $exception;
        }

        // Synced only after setRawAttributes() dropped the cached photos, else they would be merged back.
        $recipe->setRawAttributes($fresh->getAttributes())->syncOriginal();

        return $photo;
    }
}
