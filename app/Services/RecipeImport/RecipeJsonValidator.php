<?php

namespace App\Services\RecipeImport;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecipeJsonValidator
{
    /**
     * Decode and validate a raw JSON payload.
     *
     * @throws ValidationException
     */
    public function validate(string $json): ParsedRecipe
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail(__('The file does not contain valid JSON: :error', [
                'error' => json_last_error_msg(),
            ]));
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            $this->fail(__('The JSON must contain a single recipe object, not a list.'));
        }

        /** @var array<string, mixed> $decoded */
        $validator = Validator::make($decoded, $this->rules(), $this->messages());

        if ($validator->fails()) {
            throw ValidationException::withMessages(['json' => $validator->errors()->all()]);
        }

        return ParsedRecipe::fromArray($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $ingredients = [
            '.amount' => ['nullable', 'numeric', 'min:0'],
            '.amount_max' => ['nullable', 'numeric', 'min:0'],
            '.unit' => ['nullable', 'string', 'max:'.RecipeJsonSchema::MAX_UNIT_NAME],
            '.food' => ['required', 'string', 'max:'.RecipeJsonSchema::MAX_FOOD_NAME],
            '.attributes' => ['nullable', 'array'],
            '.attributes.*' => ['string', 'max:'.RecipeJsonSchema::MAX_ATTRIBUTE_NAME],
        ];

        $rules = [
            'name' => ['required', 'string', 'max:'.RecipeJsonSchema::MAX_NAME],
            'category' => ['required', 'string', 'max:'.RecipeJsonSchema::MAX_CATEGORY_NAME],
            'author' => ['nullable', 'string', 'max:'.RecipeJsonSchema::MAX_AUTHOR_NAME],
            'cookbook' => ['nullable', 'string', 'max:'.RecipeJsonSchema::MAX_COOKBOOK_NAME],
            'servings' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'serving_type' => ['nullable', 'string', 'max:'.RecipeJsonSchema::MAX_SERVING_TYPE],
            'complexity' => ['required', 'string', 'in:'.implode(',', RecipeJsonSchema::complexities())],
            'preparation_time' => ['nullable', 'string', 'date_format:H:i'],
            'instructions' => ['required', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:'.RecipeJsonSchema::MAX_TAG_NAME],
            'ingredients' => ['nullable', 'array'],
            'ingredient_groups' => ['nullable', 'array'],
            'ingredient_groups.*.name' => ['required', 'string', 'max:'.RecipeJsonSchema::MAX_GROUP_NAME],
            'ingredient_groups.*.ingredients' => ['required', 'array', 'min:1'],
            'photos' => ['nullable', 'array', 'max:'.RecipeJsonSchema::MAX_PHOTOS],
            'photos.*.filename' => ['nullable', 'string', 'max:255'],
            'photos.*.data' => ['required', 'string'],
        ];

        foreach ($ingredients as $suffix => $rule) {
            $rules['ingredients.*'.$suffix] = $rule;
            $rules['ingredients.*.alternatives.*'.$suffix] = $rule;
            $rules['ingredient_groups.*.ingredients.*'.$suffix] = $rule;
            $rules['ingredient_groups.*.ingredients.*.alternatives.*'.$suffix] = $rule;
        }

        $rules['ingredients.*.alternatives'] = ['nullable', 'array'];
        $rules['ingredient_groups.*.ingredients.*.alternatives'] = ['nullable', 'array'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'complexity.in' => __('The complexity must be one of: :values.', [
                'values' => implode(', ', RecipeJsonSchema::complexities()),
            ]),
            'preparation_time.date_format' => __('The preparation time must be formatted as HH:MM.'),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['json' => $message]);
    }
}
