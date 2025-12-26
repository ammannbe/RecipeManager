<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
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
