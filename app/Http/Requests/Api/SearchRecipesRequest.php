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
            'season_month' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function foodIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->validated('food_ids'))));
    }

    /**
     * Month (1–12) whose in-season foods rank recipes after the matched foods, if given.
     */
    public function seasonMonth(): ?int
    {
        $month = $this->validated('season_month');

        return $month === null ? null : (int) $month;
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? 20);
    }
}
