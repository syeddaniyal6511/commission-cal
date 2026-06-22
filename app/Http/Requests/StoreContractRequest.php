<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_no'     => 'required|string|max:50|unique:contracts,contract_no',
            'annual_usage'    => 'required|numeric|min:0',
            'contract_value'  => 'required|numeric|min:0',
            'contract_length' => 'required|integer|min:1|max:360',
            'risk_score'      => 'required|numeric|min:0|max:100',
        ];
    }
}
