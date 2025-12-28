<?php

namespace App\ValueObjects;

use App\Casts\AsDocuments;
use App\Services\Document;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Document>
 */
class Documents extends Collection implements Castable
{
    /**
     * Create a new Documents instance from an array.
     *
     * @param  ?array<string>  $items
     */
    public static function fromArray(string $disk, ?string $directory = null, ?array $items = null): self
    {
        $items = array_map(function ($photo) use ($disk, $directory) {
            return new Document(implode('/', [$directory, $photo]), $disk);
        }, $items ?? []);

        return new self($items);
    }

    /**
     * @param  array<int, Document>  $items
     */
    public function __construct(
        protected $items = [],
    ) {}

    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     * @return class-string<AsDocuments>
     */
    public static function castUsing(array $arguments): string
    {
        return AsDocuments::class;
    }
}
