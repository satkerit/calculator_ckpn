<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_role' => 'required|string|unique:roles,kode_role|max:50',
            'nama_role' => 'required|string|max:100',
            'deskripsi' => 'nullable|string|max:500',
            'active' => 'boolean',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ];
    }
}
