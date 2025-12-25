<?php

namespace Database\Factories\Recipes;

use App\Models\Recipes\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Some example category names.
     *
     * @var array
     */
    protected $categories = [
        'Apéros',
        'Brotaufstrich',
        'Brote',
        'Brotspeisen',
        'Cakes, Kuchen, Torten (süss)',
        'Cremen, Desserts, Eis, Puddinge',
        'Eierspeisen',
        'Eingemachtes',
        'Fleischgerichte',
        'Getränke',
        'Guetsli, Kleingebäck, Pâtisserie',
        'Saucen',
        'Sirup',
        'Suppen',
        'Süsse Gerichte',
        'Teigwarengerichte',
        'Vegetarisch',
    ];

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->unique()->randomElement($this->categories),
        ];
    }
}
