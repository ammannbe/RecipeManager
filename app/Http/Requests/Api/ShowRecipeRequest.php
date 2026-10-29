<?php

namespace App\Http\Requests\Api;

use App\Models\Recipe;
use Illuminate\Foundation\Http\FormRequest;

class ShowRecipeRequest extends FormRequest
{
    /**
     * 404 rather than 403 (and before validation) so a private recipe's existence is not leaked.
     */
    public function authorize(): bool
    {
        abort_unless((bool) $this->user()?->can('view', $this->recipe()), 404);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'servings' => ['sometimes', 'nullable', 'numeric', 'min:0.5', 'max:100'],
        ];
    }

    public function servings(): ?float
    {
        $servings = $this->validated('servings');

        return $servings !== null ? (float) $servings : null;
    }

    public function recipe(): Recipe
    {
        /** @var Recipe */
        return $this->route('recipe');
    }
}
