<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IngredientDetailFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'amount'                => ['required', 'numeric'],
            'unit_id'               => ['required', 'integer'],
            'ingredient_id'         => ['required', 'integer'],
            'prep_id'               => ['required', 'integer'],
            'position'              => ['nullable', 'integer'],
            'ingredient_detail_id'  => ['nullable', 'integer'],
        ];
    }
}
