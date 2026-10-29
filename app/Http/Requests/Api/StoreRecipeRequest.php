<?php

namespace App\Http\Requests\Api;

use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeImport\RecipeJsonSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Recipe::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.RecipeJsonSchema::MAX_NAME],
            // The column holds whole numbers only (decimal(3,0)).
            'servings' => ['nullable', 'integer', 'min:1', 'max:999'],
            'instructions' => ['required', 'string', 'max:20000'],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*' => ['required', 'array'],
            'ingredients.*.amount' => ['nullable', 'required_with:ingredients.*.amount_max', 'numeric', 'min:0', 'max:99999'],
            'ingredients.*.amount_max' => ['nullable', 'numeric', 'min:0', 'max:99999', 'gte:ingredients.*.amount'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:'.RecipeJsonSchema::MAX_UNIT_NAME],
            'ingredients.*.food' => ['required', 'string', 'max:'.RecipeJsonSchema::MAX_FOOD_NAME],
        ];
    }

    /**
     * Mirrors the duplicate guard of the JSON import: a recipe outside a cookbook
     * must have a unique name per author, trashed ones included.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $user */
                $user = $this->user();
                $name = trim((string) $this->input('name'));

                if ($validator->errors()->has('name') || $name === '') {
                    return;
                }

                $exists = Recipe::withTrashed()
                    ->where('author_id', $user->author_id)
                    ->whereNull('cookbook_id')
                    ->where('name', $name)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('name', __('You already have a recipe named ":name".', ['name' => $name]));
                }
            },
        ];
    }
}
