<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'name_shortcut',
        'name_plural',
        'name_plural_shortcut',
    ];

    /**
     * @return Attribute<string, never>
     */
    public function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => \Str::slug($this->name),
        );
    }

    public function getMatchingName(int|float|null $amount = null): string
    {
        if ($amount === null || $amount >= 1 || $amount === 0 || $amount === 0.0) {
            return $this->name_plural_shortcut
                ?? $this->name_shortcut
                ?? $this->name_plural
                ?? $this->name;
        }

        return $this->name_shortcut
            ?? $this->name_plural_shortcut
            ?? $this->name
            ?? $this->name_plural;
    }

    /**
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }
}
