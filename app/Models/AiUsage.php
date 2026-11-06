<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tokens and images of one AI request, for billing by usage.
 *
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $images
 */
class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'feature',
        'model',
        'input_tokens',
        'output_tokens',
        'images',
    ];

    /**
     * Readable name of a feature (recipe, image, image_edit, conversion).
     */
    public static function featureLabel(string $feature): string
    {
        return match ($feature) {
            'recipe' => __('Recipe'),
            'image' => __('Photo'),
            'image_edit' => __('Photo enhancement'),
            'conversion' => __('Conversions'),
            default => $feature,
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
