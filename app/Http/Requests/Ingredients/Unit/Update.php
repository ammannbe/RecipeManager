<?php

namespace App\Http\Requests\Ingredients\Unit;

use Illuminate\Foundation\Http\FormRequest;

class Update extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'                 => ['required', 'string', 'max:20', "unique:units,name,{$this->unit->id}"],
            'name_shortcut'        => ['required', 'nullable', 'string', 'max:20'],
            'name_plural'          => ['required', 'nullable', 'string', 'max:20'],
            'name_plural_shortcut' => ['required', 'nullable', 'string', 'max:20'],
        ];
    }
}
