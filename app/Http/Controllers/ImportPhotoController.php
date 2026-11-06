<?php

namespace App\Http\Controllers;

use App\Services\RecipePhotos;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the AI photo the import wizard generated, only to the user it belongs to.
 */
class ImportPhotoController extends Controller
{
    public function __invoke(string $filename): StreamedResponse
    {
        $path = RecipePhotos::importPath((int) user()?->getKey(), $filename);

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }
}
