<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Document implements \JsonSerializable
{
    public function __construct(
        protected string $path,
        protected string $disk = 'local',
        protected ?string $source = null,
        protected bool $isAiGenerated = false,
    ) {}

    public function source(): ?string
    {
        return $this->source;
    }

    public function isAiGenerated(): bool
    {
        return $this->isAiGenerated;
    }

    protected function disk(): Filesystem
    {
        return \Storage::disk($this->disk);
    }

    public function exists(): bool
    {
        return $this->disk()->exists($this->path);
    }

    public function name(bool $urlencode = false): string
    {
        $name = basename($this->path);

        if ($urlencode) {
            return rawurlencode(\Str::of($name)->replace('/', ''));
        }

        return $name;
    }

    public function path(): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        return $this->disk()->path($this->path);
    }

    public function lastModified(): ?int
    {
        if (! $this->exists()) {
            return null;
        }

        return $this->disk()->lastModified($this->path);
    }

    public function mimeType(): string|false|null
    {
        if (! $this->exists()) {
            return null;
        }

        return $this->disk()->mimeType($this->path);
    }

    public function isPreviewable(): bool
    {
        return match ($this->mimeType()) {
            'image/jpeg' => true,
            'image/png' => true,
            'image/gif' => true,
            default => false,
        };
    }

    public function size(): ?int
    {
        if (! $this->exists()) {
            return null;
        }

        return $this->disk()->size($this->path);
    }

    public function upload(TemporaryUploadedFile $file): string|false
    {
        $path = \Str::of($this->path)->beforeLast('/');
        $filename = \Str::of($this->path)->afterLast('/');

        return $file->storeAs($path, $filename, ['disk' => $this->disk]);
    }

    public function delete(): bool
    {
        return $this->disk()->delete($this->path);
    }

    public function url(): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        // Recipe photos live on a private disk and are served through an authorized route.
        if ($this->disk === 'recipes') {
            [$recipe, $filename] = array_pad(explode('/', $this->path, 2), 2, null);

            if ($recipe !== null && $filename !== null) {
                return route('recipes.photo', ['recipe' => $recipe, 'filename' => $filename]);
            }
        }

        return $this->disk()->url($this->path);
    }

    public function response(): ?BinaryFileResponse
    {
        if (! $this->path()) {
            return null;
        }

        return response()->file($this->path());
    }

    public function download(): ?BinaryFileResponse
    {
        if (! $this->path()) {
            return null;
        }

        return response()->download($this->path(), $this->name(urlencode: true));
    }

    /**
     * @return array{path: string, source: ?string, is_ai_generated: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'path' => $this->name(),
            'source' => $this->source,
            'is_ai_generated' => $this->isAiGenerated,
        ];
    }
}
