<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version'                         => 'required|string|max:50|unique:formulas,version',
            'expression'                      => 'required|string|max:2000',
            'variables'                       => 'array|max:8',
            'variables.*.name'                => 'required|string|max:100',
            'variables.*.expression'          => 'required|string|max:2000',
            'variables.*.execution_order'     => 'integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'version.required'               => 'Formula version is required.',
            'expression.required'            => 'Main expression is required.',
            'variables.*.name.required'      => 'Each sub-variable must have a name.',
            'variables.*.expression.required' => 'Each sub-variable must have an expression.',
        ];
    }
}
