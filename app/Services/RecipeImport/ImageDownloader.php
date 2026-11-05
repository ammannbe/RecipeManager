<?php

namespace App\Services\RecipeImport;

use App\Services\PublicHttp;
use App\Services\PublicUrlException;
use App\Services\RecipePhotos;
use InvalidArgumentException;

class ImageDownloader
{
    public function __construct(
        private readonly PublicHttp $http,
        private readonly RecipePhotos $photos,
    ) {}

    /**
     * Downloads an image from a public URL, or null when it is not an allowed image, too large
     * or not reachable (the import skips such photos).
     *
     * @return array{0: string, 1: string}|null binary contents and file extension
     */
    public function download(string $url, int $maxBytes = RecipeJsonSchema::MAX_PHOTO_BYTES): ?array
    {
        try {
            return $this->fetch($url, $maxBytes);
        } catch (PublicUrlException|InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Same checks as base64 photos (RecipePhotos); only public hosts, redirects checked again.
     *
     * @return array{0: string, 1: string} binary contents and file extension
     *
     * @throws PublicUrlException|InvalidArgumentException with a message for the user
     */
    public function fetch(string $url, int $maxBytes = RecipeJsonSchema::MAX_PHOTO_BYTES): array
    {
        $binary = $this->http->get($url, $maxBytes, 10, ['Accept' => 'image/*']);

        return [$binary, $this->photos->extension($binary, $maxBytes)];
    }
}
