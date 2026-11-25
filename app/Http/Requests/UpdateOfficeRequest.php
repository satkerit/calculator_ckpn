<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $officeId = $this->route('office')->id;

        return [
            'office_code' => 'required|string|max:50|unique:offices,office_code,' . $officeId,
            'office_name' => 'required|string|max:255',
            'office_type' => 'required|in:PUSAT,CABANG,UNIT',
            'province' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'active' => 'boolean',
        ];
    }
}
