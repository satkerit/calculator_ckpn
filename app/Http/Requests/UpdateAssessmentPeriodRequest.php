<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentId = $this->route('assessment')?->id;

        return [
            'period_name' => 'required|string|max:255',
            'fiscal_month' => [
                'required', 'numeric', 'min:1', 'max:12',
                // Cegah duplikat periode: kombinasi tahun+bulan harus unik (kecuali record ini sendiri).
                Rule::unique('assessment_periods', 'fiscal_month')
                    ->where(fn ($query) => $query->where('fiscal_year', $this->input('fiscal_year')))
                    ->ignore($currentId),
            ],
            'fiscal_year' => 'required|numeric|min:2000',
            'assessment_date' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'fiscal_month.unique' => 'Periode assessment untuk tahun dan bulan ini sudah ada.',
        ];
    }
}
