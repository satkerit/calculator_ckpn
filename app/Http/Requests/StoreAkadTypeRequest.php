<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAkadTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_akad' => 'required|string|max:50|unique:akad_types',
            'nama_akad' => 'required|string|max:255',
            'kategori' => 'required|in:jual_beli,bagi_hasil,sewa,pinjaman',
            'ead_rule_type' => 'required|in:outstanding_balance,principal_arrears_conditional,zero_if_not_expired',
            'requires_due_date' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
