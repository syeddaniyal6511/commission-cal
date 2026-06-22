<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_no'     => ['required', 'string', 'max:50', Rule::unique('contracts', 'contract_no')->ignore($this->route('contract'))],
            'annual_usage'    => 'required|numeric|min:0',
            'contract_value'  => 'required|numeric|min:0',
            'contract_length' => 'required|integer|min:1|max:360',
            'risk_score'      => 'required|numeric|min:0|max:100',
        ];
    }
}
