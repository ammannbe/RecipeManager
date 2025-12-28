<?php

namespace App\Casts;

use App\Services\Document;
use App\ValueObjects\Documents;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<Documents, array<string>>
 */
class AsDocuments implements CastsAttributes
{
    public function __construct(
        protected string $disk,
    ) {}

    /**
     * @param  string|null  $value
     * @param  array<string, mixed>  $attributes
     * @return Documents<int, Document>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Documents
    {
        $documents = json_decode($value ?? '[]', true);

        return Documents::fromArray($this->disk, (string) $model->getKey(), $documents ?? []);
    }

    /**
     * @param  Documents<int, Document>|array<string>|null  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string|false
    {
        if (is_array($value) || is_null($value)) {
            $value = Documents::fromArray($this->disk, (string) $model->getKey(), $value);
        }

        return json_encode($value);
    }
}
