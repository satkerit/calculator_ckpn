<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBucketDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('bucket')?->id;

        return [
            'kode_bucket' => 'required|string|max:20|unique:bucket_definitions,kode_bucket' . ($ignoreId ? ',' . $ignoreId : ''),
            'deskripsi_hari' => 'required|string|max:100',
            'hari_min' => 'required|integer|min:0',
            'hari_max' => 'nullable|integer|min:0|gte:hari_min',
            'urutan' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'kode_bucket.unique' => 'Kode bucket sudah digunakan',
            'hari_max.gte' => 'Hari maksimum harus lebih besar atau sama dengan hari minimum',
        ];
    }
}
