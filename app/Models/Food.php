<?php

namespace App\Models;

use App\Enums\Measure;
use App\Models\Concerns\Requestable;
use Database\Factories\FoodFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<int, int>|null $season_months Months (1–12) of fresh Swiss harvest
 * @property array<int, int>|null $storage_months Months available from Swiss storage only, not in season
 * @property Measure|null $measure the measure its amounts are converted to
 * @property float|null $density grams per milliliter
 */
class Food extends Model
{
    /** @use HasFactory<FoodFactory> */
    use HasFactory;

    use Requestable;
    use SoftDeletes;

    protected $table = 'foods';

    protected $fillable = [
        'name',
        'season_months',
        'storage_months',
        'measure',
        'density',
    ];

    protected $casts = [
        'season_months' => 'array',
        'storage_months' => 'array',
        'measure' => Measure::class,
        'density' => 'float',
    ];

    /**
     * Fresh harvest only; storage months do not count as in season.
     */
    public function inSeason(int $month): bool
    {
        return in_array($month, $this->season_months ?? [], true);
    }

    public function hasSeasonData(): bool
    {
        return $this->season_months !== null;
    }

    /**
     * @return HasMany<Ingredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    /**
     * Grams per piece unit (Stück, Zehe, Bund, …) of this food.
     *
     * @return HasMany<FoodUnitWeight, $this>
     */
    public function unitWeights(): HasMany
    {
        return $this->hasMany(FoodUnitWeight::class);
    }

    /**
     * @return Attribute<array<int, int>|null, mixed>
     */
    protected function seasonMonths(): Attribute
    {
        return Attribute::set(fn (mixed $value): ?string => self::encodeMonths($value));
    }

    /**
     * @return Attribute<array<int, int>|null, mixed>
     */
    protected function storageMonths(): Attribute
    {
        return Attribute::set(fn (mixed $value): ?string => self::encodeMonths($value));
    }

    /**
     * Sorted integers, so JSON_CONTAINS() matches form input ("5") too; no months is no data.
     */
    private static function encodeMonths(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $months = collect(is_array($value) ? $value : [])
            ->map(fn (mixed $month): int => (int) $month)
            ->filter(fn (int $month): bool => $month >= 1 && $month <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $months === [] ? null : (string) json_encode($months);
    }
}
