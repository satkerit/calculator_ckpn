<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollateralSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collateral_type' => 'required|string|max:100|unique:collateral_setup,collateral_type',
            'collateral_ket' => 'required|string|max:255',
            'active' => 'boolean',
        ];
    }
}
