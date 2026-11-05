<?php

namespace App\Services;

use App\Models\Recipe;
use App\Services\RecipeImport\RecipeJsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
     * Stores the image and appends it to the recipe's photos.
     *
     * @return array{path: string, source: string|null, is_ai_generated: bool}
     */
    public function add(Recipe $recipe, string $binary, string $extension, ?string $source, bool $isAiGenerated, string $suffix = ''): array
    {
        $filename = (Str::slug($recipe->name) ?: 'photo').($suffix !== '' ? '-'.$suffix : '').'-'.Str::lower(Str::random(6)).'.'.$extension;
        Storage::disk('recipes')->put($recipe->getKey().'/'.$filename, $binary);

        $photo = ['path' => $filename, 'source' => $source, 'is_ai_generated' => $isAiGenerated];

        $photos = array_map(fn (Document $document): array => $document->jsonSerialize(), array_values($recipe->photos->all()));
        $photos[] = $photo;

        $recipe->photos = $photos;
        $recipe->save();

        return $photo;
    }
}
