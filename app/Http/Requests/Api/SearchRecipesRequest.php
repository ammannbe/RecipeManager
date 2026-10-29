<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SearchRecipesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'food_ids' => ['required', 'array', 'min:1', 'max:200'],
            'food_ids.*' => ['required', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function foodIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->validated('food_ids'))));
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? 20);
    }
}
