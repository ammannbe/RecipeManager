<?php

namespace App\Livewire\Forms;

use App\Models\Recipe;
use Livewire\Attributes\Validate;
use Livewire\Form;

class RecipeForm extends Form
{
    public ?Recipe $recipe = null;

    #[Validate(['nullable', 'exists:cookbooks,id'])]
    public ?int $cookbook_id = null;

    #[Validate(['nullable', 'exists:categories,id'])]
    public ?int $category_id = null;

    #[Validate(['required', 'max:100'])]
    public string $name;

    public function setValues(Recipe $recipe): void
    {
        $this->recipe = $recipe;

        $this->cookbook_id = $recipe->cookbook_id;
        $this->category_id = $recipe->category_id;
        $this->name = $recipe->name;
    }
}
