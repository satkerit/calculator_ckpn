<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollateralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_number' => 'required|string|exists:snapshot,loan_number',
            'collateral_code' => 'nullable|string|max:50',
            'collateral_order' => 'required|integer|min:1',
            'collateral_type' => 'required|string|exists:collateral_setup,collateral_type',
            'liquidity_value' => 'nullable|numeric|min:0',
            'estimated_value' => 'nullable|numeric|min:0',
            'valuation_date' => 'nullable|date',
            'active_flag' => 'boolean',
        ];
    }
}
