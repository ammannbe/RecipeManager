<?php

namespace App\Services\RecipeImport;

use Illuminate\Support\Facades\Http;

class ImageDownloader
{
    /**
     * Downloads an image from a public URL, rejecting anything that is not an allowed
     * image type or exceeds the size limit. Mirrors the base64 decoding checks so both
     * import paths enforce the same constraints.
     *
     * @return array{0: string, 1: string}|null binary contents and file extension
     */
    public function download(string $url): ?array
    {
        try {
            $response = Http::timeout(10)->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $binary = $response->body();

        if ($binary === '' || strlen($binary) > RecipeJsonSchema::MAX_PHOTO_BYTES) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);

        if (! is_string($mime) || ! in_array($mime, RecipeJsonSchema::ALLOWED_PHOTO_MIMES, true)) {
            return null;
        }

        return [$binary, match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        }];
    }
}
