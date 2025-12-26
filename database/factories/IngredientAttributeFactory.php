<?php

namespace Database\Factories;

use App\Models\IngredientAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

class IngredientAttributeFactory extends Factory
{
    /**
     * Some example category names.
     *
     * @var array
     */
    protected $ingredientAttributes = [
        'gerieben',
        'schwarz',
        'gehackt',
        'weiss',
        'säuerlich',
        'gross',
        'gemahlen',
        'dunkel',
        'gewürfelt',
        'heiss',
        'geschnetzelt',
        'eingesotten',
        'gedörrt',
        'lauwarm',
        'grob',
        'geschält',
        'in Scheiben',
        'warm',
        'gehobelt',
        'reif',
        'fein geschnitten',
        'rund',
        'klein',
        'hell',
        'flüssig',
        'in Vierteln',
        'grob gehackt',
        'weich',
        'zartbitter',
        'instant',
        'kandiert',
        'fein gerieben',
        'ausgeschabt',
        'gekocht',
        'fein',
        '1 gr. od. 2 kl.',
        'leicht verklopft',
        'zum Bestäuben',
    ];

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->unique()->randomElement($this->ingredientAttributes),
        ];
    }
}
