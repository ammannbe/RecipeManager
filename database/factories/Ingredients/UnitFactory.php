<?php

namespace Database\Factories\Ingredients;

use App\Models\Ingredients\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $name = $this->faker->unique()->word;
        $nameShortcut = substr($name, 0, -2);
        $namePlural = \Str::plural($name);
        $namePluralShortcut = substr($namePlural, 0, -2);

        return [
            'name' => $name,
            'name_shortcut' => $nameShortcut ? $nameShortcut : null,
            'name_plural' => $namePlural,
            'name_plural_shortcut' => $namePluralShortcut ? $namePluralShortcut : null,
        ];
    }
}
