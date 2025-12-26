<?php

namespace App\Services;

use Illuminate\Filesystem\LocalFilesystemAdapter;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Document
{
    public function __construct(
        protected string $path,
        protected string $disk = 'local',
    ) {}

    protected function disk(): LocalFilesystemAdapter
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
}
