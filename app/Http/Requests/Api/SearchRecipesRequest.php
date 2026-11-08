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
            // Name search (e.g. picking a recipe by hand), food search, or both.
            'query' => ['required_without:food_ids', 'nullable', 'string', 'min:2', 'max:100'],
            'food_ids' => ['required_without:query', 'array', 'min:1', 'max:200'],
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
     * Part of the recipe name, null without a name search.
     */
    public function nameQuery(): ?string
    {
        $query = trim((string) $this->validated('query'));

        return $query === '' ? null : $query;
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
